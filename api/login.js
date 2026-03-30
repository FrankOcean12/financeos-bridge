import mysql from 'mysql2/promise';

const dbConfig = {
  host: 'sql313.infinityfree.com',
  user: 'if0_41471387',
  password: 'fishfillet12',
  database: 'if0_41471387_finance_db',
  connectTimeout: 10000,
};

export default async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
  res.setHeader('Content-Type', 'application/json');

  if (req.method === 'OPTIONS') return res.status(200).end();
  if (req.method !== 'POST') return res.status(405).json({ status: 'error', message: 'Method Not Allowed' });

  const { email, login_code } = req.body || {};

  if (!email || !login_code) {
    return res.status(400).json({ status: 'error', message: 'Email and login code are required.' });
  }

  let connection;
  try {
    connection = await mysql.createConnection(dbConfig);

    // Find customer by email
    const [rows] = await connection.execute(
      'SELECT c.*, t.org_name as tenant_org_name FROM customers c LEFT JOIN tenants t ON c.tenant_id = t.id WHERE c.email = ? LIMIT 1',
      [email]
    );

    if (rows.length === 0) {
      return res.status(200).json({ status: 'error', message: 'No account found with that email.' });
    }

    const customer = rows[0];

    // Normalize the code: remove dashes and compare
    const cleanInput = login_code.replace(/-/g, '').toUpperCase();
    const cleanStored = (customer.login_code || '').replace(/-/g, '').toUpperCase();

    if (cleanInput !== cleanStored) {
      return res.status(200).json({ status: 'error', message: 'Invalid login code. Please check and try again.' });
    }

    // Generate a session token
    const token = Buffer.from(`${customer.id}:${Date.now()}`).toString('base64');

    // Update last login
    await connection.execute('UPDATE customers SET last_login = NOW() WHERE id = ?', [customer.id]);

    return res.status(200).json({
      status: 'success',
      message: 'Login successful',
      data: {
        token: token,
        customer_id: customer.id,
        tenant_id: customer.tenant_id,
        first_name: customer.first_name,
        last_name: customer.last_name,
        email: customer.email,
        tenant_org_name: customer.tenant_org_name || 'FinanceOS',
      }
    });

  } catch (error) {
    return res.status(500).json({ status: 'error', message: 'DB Error: ' + error.message });
  } finally {
    if (connection) await connection.end();
  }
}
