'use strict';

require('dotenv').config({ quiet: true });

const app = require('../server');
const pool = require('../lib/db');

let failures = 0;
function check(name, condition, extra = '') {
  console.log(`${condition ? 'PASS' : 'FAIL'} ${name}${extra ? ' ' + extra : ''}`);
  if (!condition) failures++;
}

function makeClient(base) {
  const cookies = new Map();

  function cookieHeader() {
    return [...cookies.entries()].map(([k, v]) => `${k}=${v}`).join('; ');
  }

  function store(response) {
    const lines = response.headers.getSetCookie ? response.headers.getSetCookie() : [];
    for (const line of lines) {
      const pair = line.split(';')[0];
      const index = pair.indexOf('=');
      const name = pair.slice(0, index);
      const value = pair.slice(index + 1);
      if (!value) cookies.delete(name);
      else cookies.set(name, value);
    }
  }

  async function request(path, options = {}) {
    const headers = { ...(options.headers || {}) };
    const cookie = cookieHeader();
    if (cookie) headers.cookie = cookie;
    if (options.body) headers['content-type'] = 'application/x-www-form-urlencoded';
    const response = await fetch(base + path, { ...options, headers, redirect: 'manual' });
    store(response);
    return response;
  }

  return {
    get: (path) => request(path),
    post: (path, body) => request(path, { method: 'POST', body: new URLSearchParams(body) }),
    request,
  };
}

function csrfFrom(html) {
  const match = html.match(/name="_csrf" value="([a-f0-9]+)"/);
  return match ? match[1] : '';
}

async function login(client, email, password) {
  const page = await client.get('/login');
  const csrf = csrfFrom(await page.text());
  return client.post('/login', { _csrf: csrf, email, password });
}

async function main() {
  const server = app.listen(0);
  await new Promise((resolve) => server.once('listening', resolve));
  const base = `http://127.0.0.1:${server.address().port}`;

  const anon = makeClient(base);
  const anonymous = await anon.get('/buyer/catalog');
  check('anon buyer catalog redirects to login', anonymous.status === 302 && anonymous.headers.get('location') === '/login');

  const admin = makeClient(base);
  const adminLogin = await login(admin, 'admin@tokoosing.test', 'admin123');
  check('admin login redirect', adminLogin.status === 302 && adminLogin.headers.get('location') === '/admin/users');

  const adminPage = await admin.get('/admin/users');
  const adminHtml = await adminPage.text();
  check('admin page renders', adminPage.status === 200 && adminHtml.includes('Kelola Pengguna'));
  const adminCsrf = csrfFrom(adminHtml);

  const createUser = await admin.post('/admin/users', {
    _csrf: adminCsrf,
    action: 'create',
    nama: 'Uji Smoke',
    email: 'smoke@tokoosing.test',
    whatsapp: '081234567890',
    role: 'buyer',
    password: 'rahasia123',
    is_active: '1',
  });
  check('create user redirects', createUser.status === 302);

  const [[createdUser]] = await pool.query("SELECT id, role FROM users WHERE email = 'smoke@tokoosing.test'");
  check('created user in database', Boolean(createdUser) && createdUser.role === 'buyer');

  const updateUser = await admin.post('/admin/users', {
    _csrf: adminCsrf,
    action: 'update',
    id: createdUser.id,
    nama: 'Uji Smoke Edit',
    email: 'smoke@tokoosing.test',
    whatsapp: '081234567890',
    role: 'buyer',
    is_active: '1',
  });
  const [[updatedUser]] = await pool.query('SELECT nama FROM users WHERE id = ?', [createdUser.id]);
  check('update user persists', updateUser.status === 302 && updatedUser.nama === 'Uji Smoke Edit');

  const deleteUser = await admin.post('/admin/users', {
    _csrf: adminCsrf,
    action: 'delete',
    id: createdUser.id,
  });
  const [[deletedUser]] = await pool.query('SELECT COUNT(*) AS total FROM users WHERE id = ?', [createdUser.id]);
  check('delete user persists', deleteUser.status === 302 && Number(deletedUser.total) === 0);

  const seller = makeClient(base);
  const sellerLogin = await login(seller, 'batik.kemiren@tokoosing.test', 'seller123');
  check('seller login redirect', sellerLogin.status === 302 && sellerLogin.headers.get('location') === '/seller/products');

  const sellerPage = await seller.get('/seller/products');
  const sellerHtml = await sellerPage.text();
  check('seller page renders', sellerPage.status === 200 && sellerHtml.includes('Produk &amp; Harga'));
  const sellerCsrf = csrfFrom(sellerHtml);

  const createProduct = await seller.post('/seller/products', {
    _csrf: sellerCsrf,
    action: 'create',
    nama: 'Produk Uji Smoke',
    category_id: '1',
    harga: '12345',
    stok: '7',
    deskripsi: 'Produk untuk pengujian',
    unggulan: '1',
  });
  check('create product redirects', createProduct.status === 302);

  const [[createdProduct]] = await pool.query("SELECT id, seller_id, harga FROM products WHERE nama = 'Produk Uji Smoke'");
  check('created product in database', Boolean(createdProduct) && Number(createdProduct.seller_id) === 1);

  const updateProduct = await seller.post('/seller/products', {
    _csrf: sellerCsrf,
    action: 'update',
    id: createdProduct.id,
    nama: 'Produk Uji Smoke',
    category_id: '1',
    harga: '22000',
    stok: '7',
    deskripsi: 'Harga diubah',
    oleh_oleh: '1',
  });
  const [[updatedProduct]] = await pool.query('SELECT harga FROM products WHERE id = ?', [createdProduct.id]);
  check('update price persists', updateProduct.status === 302 && Number(updatedProduct.harga) === 22000);

  const deleteProduct = await seller.post('/seller/products', {
    _csrf: sellerCsrf,
    action: 'delete',
    id: createdProduct.id,
  });
  const [[deletedProduct]] = await pool.query('SELECT COUNT(*) AS total FROM products WHERE id = ?', [createdProduct.id]);
  check('delete product persists', deleteProduct.status === 302 && Number(deletedProduct.total) === 0);

  const crossSeller = await seller.post('/seller/products', {
    _csrf: sellerCsrf,
    action: 'update',
    id: 3,
    nama: 'HACKED',
    category_id: '1',
    harga: '1',
    stok: '1',
  });
  const [[otherProduct]] = await pool.query('SELECT nama FROM products WHERE id = 3');
  check('seller cannot edit another seller product', crossSeller.status === 302 && otherProduct.nama !== 'HACKED');

  const invalidCsrf = await seller.post('/seller/products', { _csrf: 'invalid', action: 'delete', id: 1 });
  check('invalid csrf rejected', invalidCsrf.status === 419);

  const createCategory = await seller.post('/seller/categories', {
    _csrf: sellerCsrf,
    action: 'create',
    nama: 'Kategori Smoke',
    deskripsi: 'Uji',
  });
  check('create category redirects', createCategory.status === 302);
  const [[createdCategory]] = await pool.query("SELECT id FROM categories WHERE nama = 'Kategori Smoke'");
  check('created category in database', Boolean(createdCategory));

  const deleteUsedCategory = await seller.post('/seller/categories', {
    _csrf: sellerCsrf,
    action: 'delete',
    id: 1,
  });
  const [[stillUsed]] = await pool.query('SELECT COUNT(*) AS total FROM categories WHERE id = 1');
  check('used category cannot be deleted', deleteUsedCategory.status === 302 && Number(stillUsed.total) === 1);

  const deleteCategory = await seller.post('/seller/categories', {
    _csrf: sellerCsrf,
    action: 'delete',
    id: createdCategory.id,
  });
  const [[deletedCategory]] = await pool.query('SELECT COUNT(*) AS total FROM categories WHERE id = ?', [createdCategory.id]);
  check('delete category persists', deleteCategory.status === 302 && Number(deletedCategory.total) === 0);

  const buyer = makeClient(base);
  const buyerLogin = await login(buyer, 'buyer@tokoosing.test', 'buyer123');
  check('buyer login redirect', buyerLogin.status === 302 && buyerLogin.headers.get('location') === '/buyer/catalog');

  const catalog = await buyer.get('/buyer/catalog?desa=Kemiren');
  const catalogHtml = await catalog.text();
  const kemirenCount = (catalogHtml.match(/<article class="product-card">/g) || []).length;
  const waCount = (catalogHtml.match(/wa\.me\/6280000000001/g) || []).length;
  check('filter desa Kemiren returns 3 products', kemirenCount === 3, `(actual ${kemirenCount})`);
  check('whatsapp links render', waCount === 2, `(actual ${waCount})`);

  const xss = await seller.post('/seller/products', {
    _csrf: sellerCsrf,
    action: 'create',
    nama: '<script>alert(1)</script>',
    category_id: '1',
    harga: '1000',
    stok: '5',
  });
  const [[xssProduct]] = await pool.query("SELECT id FROM products WHERE nama = '<script>alert(1)</script>'");
  check('xss product created for test', xss.status === 302 && Boolean(xssProduct));

  const xssCatalog = await buyer.get('/buyer/catalog?q=script');
  const xssHtml = await xssCatalog.text();
  check('xss name is escaped', xssHtml.includes('&lt;script&gt;alert(1)&lt;/script&gt;') && !xssHtml.includes('<script>alert(1)</script>'));

  const buyerAdmin = await buyer.get('/admin/users');
  check('buyer cannot access admin', buyerAdmin.status === 403);

  const sellerAdmin = await seller.get('/admin/users');
  check('seller cannot access admin', sellerAdmin.status === 403);

  await pool.query("DELETE FROM products WHERE nama IN ('Produk Uji Smoke', '<script>alert(1)</script>')");
  await pool.query("DELETE FROM categories WHERE nama = 'Kategori Smoke'");
  await pool.query("DELETE FROM users WHERE email = 'smoke@tokoosing.test'");

  await new Promise((resolve) => server.close(resolve));
  await pool.end();

  console.log(failures === 0 ? '\nSemua smoke test lulus.' : `\n${failures} smoke test gagal.`);
  process.exitCode = failures === 0 ? 0 : 1;
}

main().catch(async (error) => {
  console.error(error);
  try {
    await pool.end();
  } catch {}
  process.exitCode = 1;
});
