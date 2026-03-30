export default async function handler(req, res) {
  if (req.method !== 'POST') {
    return res.status(405).json({ error: 'Method Not Allowed' });
  }

  // Your InfinityFree Domain
  const TARGET_URL = 'https://modernfinance.ct.ws/api/customer/login.php';
  
  // The Bypass Cookie (Environment Variable recommended in Vercel)
  const TEST_COOKIE = process.env.TEST_COOKIE || '52fa0be024ef166e30eac273a076daa4';

  try {
    const response = await fetch(TARGET_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Cookie': `__test=${TEST_COOKIE}`,
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
      },
      body: JSON.stringify(req.body)
    });

    const rawText = await response.text();
    try {
      const data = JSON.parse(rawText);
      return res.status(response.status).json(data);
    } catch (e) {
      return res.status(500).json({ 
        status: 'error', 
        message: 'Raw Server Response (Non-JSON): ' + rawText.substring(0, 200) 
      });
    }
  } catch (error) {
    return res.status(500).json({ status: 'error', message: 'Vercel Bridge Error: ' + error.message });
  }
}
