'use strict';

require('dotenv').config({ quiet: true });

const path = require('path');
const express = require('express');
const cookieParser = require('cookie-parser');
const bcrypt = require('bcryptjs');

const pool = require('./lib/db');
const { attachUser, requireRole, USER_QUERY } = require('./lib/auth');
const H = require('./lib/helpers');

const app = express();

app.disable('x-powered-by');
app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));

app.use(express.urlencoded({ extended: false }));
app.use(cookieParser());
app.use(express.static(path.join(__dirname, 'public'), { maxAge: '1h' }));
app.use((req, res, next) => {
  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('Referrer-Policy', 'same-origin');
  next();
});
app.use(H.flashMiddleware);
app.use(attachUser);
app.use(H.csrfMiddleware);
app.use((req, res, next) => {
  res.locals.currentPath = req.path;
  res.locals.homeFor = H.homeFor;
  res.locals.roleLabel = H.roleLabel;
  res.locals.rupiah = H.rupiah;
  res.locals.waUrl = H.waUrl;
  res.locals.productImage = H.productImage;
  next();
});
app.use((req, res, next) => {
  if (req.method === 'POST') return H.csrfCheck(req, res, next);
  next();
});

const DESA_OPTIONS = ['Kemiren', 'Tamansari', 'Osing'];
const ROLE_OPTIONS = { buyer: 'Pembeli', seller: 'Seller', admin: 'Admin' };

function text(value, fallback = '') {
  return typeof value === 'string' ? value.trim() : fallback;
}

function intId(value) {
  const parsed = Number.parseInt(value, 10);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : 0;
}

function parseNonNegativeInt(value) {
  if (typeof value !== 'string') return null;
  const trimmed = value.trim();
  if (!/^\d+$/.test(trimmed)) return null;
  const parsed = Number(trimmed);
  return Number.isSafeInteger(parsed) && parsed <= 4294967295 ? parsed : null;
}

function validEmail(value) {
  return value.length <= 150 && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

function blankAdminForm() {
  return {
    id: 0,
    nama: '',
    email: '',
    whatsapp: '',
    role: 'buyer',
    is_active: 1,
    nama_usaha: '',
    desa: 'Kemiren',
    seller_whatsapp: '',
    profil: '',
  };
}

function adminFormFromRow(row) {
  return {
    id: Number(row.id),
    nama: String(row.nama || ''),
    email: String(row.email || ''),
    whatsapp: String(row.whatsapp || ''),
    role: String(row.role || 'buyer'),
    is_active: Number(row.is_active),
    nama_usaha: String(row.nama_usaha || ''),
    desa: String(row.desa || 'Kemiren'),
    seller_whatsapp: String(row.seller_whatsapp || ''),
    profil: String(row.profil || ''),
  };
}

function validateAdminForm(form, { isCreate, password }) {
  const errors = {};

  if (form.nama.length < 2 || form.nama.length > 100) {
    errors.nama = 'Nama wajib 2–100 karakter.';
  }
  if (!validEmail(form.email)) {
    errors.email = 'Email tidak valid.';
  }
  if (!Object.prototype.hasOwnProperty.call(ROLE_OPTIONS, form.role)) {
    errors.role = 'Pilih peran yang valid.';
  }

  let userPhone = '';
  if (form.whatsapp !== '') {
    userPhone = H.normalizePhone(form.whatsapp);
    if (!userPhone) errors.whatsapp = 'Nomor WhatsApp tidak valid.';
  }

  if (isCreate && password.length < 6) {
    errors.password = 'Kata sandi minimal 6 karakter.';
  }
  if (!isCreate && password !== '' && password.length < 6) {
    errors.password = 'Kata sandi baru minimal 6 karakter.';
  }

  let sellerPhone = '';
  if (form.role === 'seller') {
    if (form.nama_usaha.length < 2 || form.nama_usaha.length > 120) {
      errors.nama_usaha = 'Nama usaha wajib 2–120 karakter.';
    }
    if (!DESA_OPTIONS.includes(form.desa)) {
      errors.desa = 'Pilih desa yang valid.';
    }
    sellerPhone = H.normalizePhone(form.seller_whatsapp);
    if (!sellerPhone) {
      errors.seller_whatsapp = 'Nomor WhatsApp seller wajib valid (9–15 digit).';
    }
    if (form.profil.length > 255) {
      errors.profil = 'Profil maksimal 255 karakter.';
    }
  }

  return { errors, userPhone, sellerPhone };
}

async function renderAdminUsers(res, { errors = {}, form = blankAdminForm(), editing = null, status = 200 } = {}) {
  const [users] = await pool.query(
    `SELECT u.*, s.nama_usaha, s.desa
     FROM users u LEFT JOIN sellers s ON s.id = u.seller_id
     ORDER BY FIELD(u.role, 'admin', 'seller', 'buyer'), u.nama`
  );
  const [[stats]] = await pool.query(
    `SELECT COUNT(*) AS total,
            COALESCE(SUM(role = 'admin'), 0) AS admin_count,
            COALESCE(SUM(role = 'seller'), 0) AS seller_count,
            COALESCE(SUM(role = 'buyer'), 0) AS buyer_count,
            COALESCE(SUM(is_active = 0), 0) AS inactive_count
     FROM users`
  );

  res.status(status).render('admin/users', {
    pageTitle: 'Kelola Pengguna',
    users,
    stats,
    errors,
    form,
    editing,
    roleOptions: ROLE_OPTIONS,
    desaOptions: DESA_OPTIONS,
  });
}

function blankProductForm() {
  return {
    id: 0,
    nama: '',
    category_id: '',
    harga: '',
    stok: '',
    deskripsi: '',
    unggulan: 0,
    oleh_oleh: 0,
  };
}

function productFormFromRow(row) {
  return {
    id: Number(row.id),
    nama: String(row.nama || ''),
    category_id: String(row.category_id || ''),
    harga: String(row.harga ?? ''),
    stok: String(row.stok ?? ''),
    deskripsi: String(row.deskripsi || ''),
    unggulan: Number(row.unggulan),
    oleh_oleh: Number(row.oleh_oleh),
  };
}

async function renderSellerProducts(req, res, { errors = {}, form = blankProductForm(), editing = null, search = '', status = 200 } = {}) {
  const sellerId = Number(req.user.seller_id);
  const [categories] = await pool.query('SELECT id, nama FROM categories ORDER BY nama');

  let sql =
    'SELECT p.*, c.nama AS kategori FROM products p ' +
    'JOIN categories c ON c.id = p.category_id WHERE p.seller_id = ?';
  const params = [sellerId];

  if (search !== '') {
    sql += ' AND (p.nama LIKE ? OR c.nama LIKE ? OR p.deskripsi LIKE ?)';
    const like = `%${search}%`;
    params.push(like, like, like);
  }
  sql += ' ORDER BY p.unggulan DESC, p.nama';

  const [products] = await pool.query(sql, params);
  const [[stats]] = await pool.query(
    `SELECT COUNT(*) AS total,
            COALESCE(SUM(stok), 0) AS total_stok,
            COALESCE(SUM(stok = 0), 0) AS habis,
            COALESCE(SUM(stok > 0 AND stok <= 5), 0) AS stok_rendah,
            COALESCE(SUM(unggulan = 1), 0) AS unggulan
     FROM products WHERE seller_id = ?`,
    [sellerId]
  );

  res.status(status).render('seller/products', {
    pageTitle: 'Produk & Harga',
    products,
    categories,
    stats,
    errors,
    form,
    editing,
    search,
  });
}

function blankCategoryForm() {
  return { id: 0, nama: '', deskripsi: '' };
}

async function renderSellerCategories(res, { errors = {}, form = blankCategoryForm(), editing = null, status = 200 } = {}) {
  const [categories] = await pool.query(
    `SELECT c.*, COUNT(p.id) AS jumlah_produk, COALESCE(SUM(p.stok), 0) AS total_stok
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id
     ORDER BY c.nama`
  );

  res.status(status).render('seller/categories', {
    pageTitle: 'Kelola Kategori',
    categories,
    errors,
    form,
    editing,
  });
}

app.get('/', (req, res) => {
  if (!req.user) return res.redirect('/login');
  return res.redirect(H.homeFor(req.user.role));
});

app.get('/login', (req, res) => {
  if (req.user) return res.redirect(H.homeFor(req.user.role));
  return res.render('login', { pageTitle: 'Masuk', errors: {}, form: { email: '' } });
});

app.post('/login', async (req, res, next) => {
  try {
    if (req.user) return res.redirect(H.homeFor(req.user.role));

    const email = text(req.body.email).toLowerCase();
    const password = text(req.body.password);
    const errors = {};

    if (!validEmail(email)) errors.email = 'Masukkan alamat email yang valid.';
    if (password === '') errors.password = 'Kata sandi wajib diisi.';

    if (Object.keys(errors).length === 0) {
      const [rows] = await pool.query('SELECT * FROM users WHERE email = ? LIMIT 1', [email]);
      const user = rows[0];

      if (!user || !(await bcrypt.compare(password, user.password_hash))) {
        errors.general = 'Email atau kata sandi salah.';
      } else if (!Number(user.is_active)) {
        errors.general = 'Akun ini sedang dinonaktifkan. Hubungi admin.';
      } else {
        H.setAuthCookie(res, user.id);
        H.setFlash(res, `Selamat datang, ${user.nama}.`);
        return res.redirect(H.homeFor(user.role));
      }
    }

    return res.status(422).render('login', { pageTitle: 'Masuk', errors, form: { email } });
  } catch (error) {
    return next(error);
  }
});

app.get('/register', (req, res) => {
  if (req.user) return res.redirect(H.homeFor(req.user.role));
  return res.render('register', {
    pageTitle: 'Daftar Pembeli',
    errors: {},
    form: { nama: '', email: '', whatsapp: '' },
  });
});

app.post('/register', async (req, res, next) => {
  try {
    if (req.user) return res.redirect(H.homeFor(req.user.role));

    const form = {
      nama: text(req.body.nama),
      email: text(req.body.email).toLowerCase(),
      whatsapp: text(req.body.whatsapp),
    };
    const password = text(req.body.password);
    const confirm = text(req.body.password_confirm);
    const errors = {};

    if (form.nama.length < 2 || form.nama.length > 100) {
      errors.nama = 'Nama wajib diisi 2–100 karakter.';
    }
    if (!validEmail(form.email)) errors.email = 'Masukkan alamat email yang valid.';

    let phone = '';
    if (form.whatsapp !== '') {
      phone = H.normalizePhone(form.whatsapp);
      if (!phone) errors.whatsapp = 'Nomor WhatsApp tidak valid (9–15 digit).';
    }
    if (password.length < 6) errors.password = 'Kata sandi minimal 6 karakter.';
    if (password !== confirm) errors.password_confirm = 'Konfirmasi kata sandi tidak sama.';

    if (Object.keys(errors).length === 0) {
      const [existing] = await pool.query('SELECT id FROM users WHERE email = ? LIMIT 1', [form.email]);
      if (existing.length) errors.email = 'Email sudah terdaftar.';
    }

    if (Object.keys(errors).length > 0) {
      return res.status(422).render('register', { pageTitle: 'Daftar Pembeli', errors, form });
    }

    const [result] = await pool.query(
      "INSERT INTO users (nama, email, password_hash, role, whatsapp) VALUES (?, ?, ?, 'buyer', ?)",
      [form.nama, form.email, await bcrypt.hash(password, 10), phone || null]
    );

    H.setAuthCookie(res, result.insertId);
    H.setFlash(res, 'Pendaftaran berhasil. Selamat datang!');
    return res.redirect('/buyer/catalog');
  } catch (error) {
    return next(error);
  }
});

app.post('/logout', (req, res) => {
  H.clearAuthCookie(res);
  H.setFlash(res, 'Anda telah keluar.');
  return res.redirect('/login');
});

app.get('/admin/users', requireRole('admin'), async (req, res, next) => {
  try {
    let editing = null;
    let form = blankAdminForm();

    if (req.query.edit) {
      const id = intId(req.query.edit);
      if (id) {
        const [rows] = await pool.query(USER_QUERY, [id]);
        editing = rows[0] || null;
        if (editing) form = adminFormFromRow(editing);
      }
    }

    return await renderAdminUsers(res, { editing, form });
  } catch (error) {
    return next(error);
  }
});

app.post('/admin/users', requireRole('admin'), async (req, res, next) => {
  try {
    const action = text(req.body.action);

    if (action === 'delete') {
      const id = intId(req.body.id);
      const [rows] = await pool.query('SELECT id, seller_id FROM users WHERE id = ? LIMIT 1', [id]);
      const target = rows[0];

      if (!target) {
        H.setFlash(res, 'Pengguna tidak ditemukan.', 'error');
      } else if (Number(target.id) === Number(req.user.id)) {
        H.setFlash(res, 'Anda tidak dapat menghapus akun sendiri.', 'error');
      } else {
        const connection = await pool.getConnection();
        try {
          await connection.beginTransaction();
          await connection.query('DELETE FROM users WHERE id = ?', [id]);
          if (target.seller_id !== null) {
            await connection.query('DELETE FROM sellers WHERE id = ?', [Number(target.seller_id)]);
          }
          await connection.commit();
        } catch (error) {
          await connection.rollback();
          throw error;
        } finally {
          connection.release();
        }
        H.setFlash(
          res,
          'Pengguna dihapus.' + (target.seller_id !== null ? ' Produk milik seller tersebut juga dihapus.' : '')
        );
      }

      return res.redirect('/admin/users');
    }

    const isCreate = action === 'create';
    const id = intId(req.body.id);
    const password = text(req.body.password);
    const form = {
      id,
      nama: text(req.body.nama),
      email: text(req.body.email).toLowerCase(),
      whatsapp: text(req.body.whatsapp),
      role: text(req.body.role, 'buyer'),
      is_active: req.body.is_active === '1' ? 1 : 0,
      nama_usaha: text(req.body.nama_usaha),
      desa: text(req.body.desa, 'Kemiren'),
      seller_whatsapp: text(req.body.seller_whatsapp),
      profil: text(req.body.profil),
    };

    let existing = null;
    if (!isCreate) {
      const [rows] = await pool.query(USER_QUERY, [id]);
      existing = rows[0] || null;
      if (!existing) {
        H.setFlash(res, 'Pengguna tidak ditemukan.', 'error');
        return res.redirect('/admin/users');
      }
    }

    const { errors, userPhone, sellerPhone } = validateAdminForm(form, { isCreate, password });

    if (Object.keys(errors).length === 0) {
      const [duplicates] = await pool.query(
        'SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1',
        [form.email, isCreate ? 0 : id]
      );
      if (duplicates.length) errors.email = 'Email sudah dipakai pengguna lain.';
    }

    if (Object.keys(errors).length > 0) {
      return await renderAdminUsers(res, {
        errors,
        form,
        editing: isCreate ? null : form,
        status: 422,
      });
    }

    const connection = await pool.getConnection();
    try {
      await connection.beginTransaction();
      let sellerId = existing && existing.seller_id !== null ? Number(existing.seller_id) : null;

      if (form.role === 'seller') {
        if (sellerId !== null) {
          await connection.query(
            'UPDATE sellers SET nama_usaha = ?, desa = ?, profil = ?, whatsapp = ? WHERE id = ?',
            [form.nama_usaha, form.desa, form.profil, sellerPhone, sellerId]
          );
        } else {
          const [sellerResult] = await connection.query(
            'INSERT INTO sellers (nama_usaha, desa, profil, whatsapp) VALUES (?, ?, ?, ?)',
            [form.nama_usaha, form.desa, form.profil, sellerPhone]
          );
          sellerId = Number(sellerResult.insertId);
        }
      }

      if (isCreate) {
        await connection.query(
          'INSERT INTO users (nama, email, password_hash, role, whatsapp, seller_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)',
          [
            form.nama,
            form.email,
            await bcrypt.hash(password, 10),
            form.role,
            userPhone || null,
            sellerId,
            form.is_active,
          ]
        );
      } else {
        let sql = 'UPDATE users SET nama = ?, email = ?, role = ?, whatsapp = ?, seller_id = ?, is_active = ?';
        const params = [
          form.nama,
          form.email,
          form.role,
          userPhone || null,
          sellerId,
          form.is_active,
        ];
        if (password !== '') {
          sql += ', password_hash = ?';
          params.push(await bcrypt.hash(password, 10));
        }
        sql += ' WHERE id = ?';
        params.push(id);
        await connection.query(sql, params);
      }

      await connection.commit();
    } catch (error) {
      await connection.rollback();
      throw error;
    } finally {
      connection.release();
    }

    H.setFlash(res, isCreate ? 'Pengguna baru ditambahkan.' : 'Data pengguna diperbarui.');
    return res.redirect('/admin/users');
  } catch (error) {
    if (error.code === 'ER_DUP_ENTRY') {
      return await renderAdminUsers(res, {
        errors: { email: 'Email sudah dipakai pengguna lain.' },
        form: blankAdminForm(),
        status: 422,
      });
    }
    return next(error);
  }
});

app.get('/seller/products', requireRole('seller'), async (req, res, next) => {
  try {
    if (!req.user.seller_id) {
      return res.status(403).send('Akun seller ini belum terhubung ke profil usaha. Hubungi admin.');
    }

    const search = text(req.query.q);
    let editing = null;
    let form = blankProductForm();

    if (req.query.edit) {
      const id = intId(req.query.edit);
      if (id) {
        const [rows] = await pool.query(
          'SELECT * FROM products WHERE id = ? AND seller_id = ? LIMIT 1',
          [id, Number(req.user.seller_id)]
        );
        editing = rows[0] || null;
        if (editing) form = productFormFromRow(editing);
      }
    }

    return await renderSellerProducts(req, res, { editing, form, search });
  } catch (error) {
    return next(error);
  }
});

app.post('/seller/products', requireRole('seller'), async (req, res, next) => {
  try {
    const sellerId = Number(req.user.seller_id);
    if (!sellerId) {
      return res.status(403).send('Akun seller ini belum terhubung ke profil usaha. Hubungi admin.');
    }

    const action = text(req.body.action);

    if (action === 'delete') {
      const id = intId(req.body.id);
      const [result] = await pool.query('DELETE FROM products WHERE id = ? AND seller_id = ?', [id, sellerId]);
      H.setFlash(
        res,
        result.affectedRows ? 'Produk dihapus.' : 'Produk tidak ditemukan.',
        result.affectedRows ? 'success' : 'error'
      );
      return res.redirect('/seller/products');
    }

    const isCreate = action === 'create';
    const id = intId(req.body.id);
    const form = {
      id,
      nama: text(req.body.nama),
      category_id: text(req.body.category_id),
      harga: text(req.body.harga),
      stok: text(req.body.stok),
      deskripsi: text(req.body.deskripsi),
      unggulan: req.body.unggulan === '1' ? 1 : 0,
      oleh_oleh: req.body.oleh_oleh === '1' ? 1 : 0,
    };

    const [categories] = await pool.query('SELECT id FROM categories');
    const categoryIds = categories.map((row) => Number(row.id));

    let existing = null;
    if (!isCreate) {
      const [rows] = await pool.query('SELECT * FROM products WHERE id = ? AND seller_id = ? LIMIT 1', [
        id,
        sellerId,
      ]);
      existing = rows[0] || null;
      if (!existing) {
        H.setFlash(res, 'Produk tidak ditemukan atau bukan milik usaha Anda.', 'error');
        return res.redirect('/seller/products');
      }
    }

    const errors = {};
    const harga = parseNonNegativeInt(form.harga);
    const stok = parseNonNegativeInt(form.stok);

    if (form.nama.length < 2 || form.nama.length > 120) {
      errors.nama = 'Nama produk wajib 2–120 karakter.';
    }
    if (!categoryIds.includes(Number(form.category_id))) {
      errors.category_id = 'Pilih kategori yang tersedia.';
    }
    if (harga === null) errors.harga = 'Harga wajib bilangan bulat Rupiah, minimal 0.';
    if (stok === null) errors.stok = 'Stok wajib bilangan bulat, minimal 0.';
    if (form.deskripsi.length > 200) errors.deskripsi = 'Deskripsi maksimal 200 karakter.';

    if (Object.keys(errors).length > 0) {
      return await renderSellerProducts(req, res, {
        errors,
        form,
        editing: isCreate ? null : form,
        status: 422,
      });
    }

    if (isCreate) {
      await pool.query(
        `INSERT INTO products (seller_id, category_id, nama, harga, stok, deskripsi, unggulan, oleh_oleh)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
        [sellerId, Number(form.category_id), form.nama, harga, stok, form.deskripsi, form.unggulan, form.oleh_oleh]
      );
      H.setFlash(res, 'Produk baru ditambahkan.');
    } else {
      await pool.query(
        `UPDATE products
         SET category_id = ?, nama = ?, harga = ?, stok = ?, deskripsi = ?, unggulan = ?, oleh_oleh = ?
         WHERE id = ? AND seller_id = ?`,
        [
          Number(form.category_id),
          form.nama,
          harga,
          stok,
          form.deskripsi,
          form.unggulan,
          form.oleh_oleh,
          id,
          sellerId,
        ]
      );
      H.setFlash(res, 'Produk dan harga diperbarui.');
    }

    return res.redirect('/seller/products');
  } catch (error) {
    return next(error);
  }
});

app.get('/seller/categories', requireRole('seller'), async (req, res, next) => {
  try {
    let editing = null;
    let form = blankCategoryForm();

    if (req.query.edit) {
      const id = intId(req.query.edit);
      if (id) {
        const [rows] = await pool.query('SELECT * FROM categories WHERE id = ? LIMIT 1', [id]);
        editing = rows[0] || null;
        if (editing) {
          form = { id: Number(editing.id), nama: String(editing.nama), deskripsi: String(editing.deskripsi || '') };
        }
      }
    }

    return await renderSellerCategories(res, { editing, form });
  } catch (error) {
    return next(error);
  }
});

app.post('/seller/categories', requireRole('seller'), async (req, res, next) => {
  try {
    const action = text(req.body.action);

    if (action === 'delete') {
      const id = intId(req.body.id);
      const [[used]] = await pool.query('SELECT COUNT(*) AS total FROM products WHERE category_id = ?', [id]);

      if (Number(used.total) > 0) {
        H.setFlash(res, `Kategori tidak dapat dihapus karena masih dipakai ${used.total} produk.`, 'error');
      } else {
        const [result] = await pool.query('DELETE FROM categories WHERE id = ?', [id]);
        H.setFlash(res, result.affectedRows ? 'Kategori dihapus.' : 'Kategori tidak ditemukan.', result.affectedRows ? 'success' : 'error');
      }

      return res.redirect('/seller/categories');
    }

    const isCreate = action === 'create';
    const id = intId(req.body.id);
    const form = {
      id,
      nama: text(req.body.nama),
      deskripsi: text(req.body.deskripsi),
    };

    if (!isCreate) {
      const [rows] = await pool.query('SELECT * FROM categories WHERE id = ? LIMIT 1', [id]);
      if (!rows[0]) {
        H.setFlash(res, 'Kategori tidak ditemukan.', 'error');
        return res.redirect('/seller/categories');
      }
    }

    const errors = {};
    const slug = H.slugify(form.nama);

    if (form.nama.length < 2 || form.nama.length > 60) {
      errors.nama = 'Nama kategori wajib 2–60 karakter.';
    }
    if (form.deskripsi.length > 200) {
      errors.deskripsi = 'Deskripsi maksimal 200 karakter.';
    }
    if (!errors.nama && slug === '') {
      errors.nama = 'Nama kategori harus memuat huruf atau angka.';
    }

    if (Object.keys(errors).length === 0) {
      const [duplicates] = await pool.query(
        'SELECT id FROM categories WHERE (nama = ? OR slug = ?) AND id <> ? LIMIT 1',
        [form.nama, slug, isCreate ? 0 : id]
      );
      if (duplicates.length) errors.nama = 'Kategori dengan nama serupa sudah ada.';
    }

    if (Object.keys(errors).length > 0) {
      return await renderSellerCategories(res, {
        errors,
        form,
        editing: isCreate ? null : form,
        status: 422,
      });
    }

    if (isCreate) {
      await pool.query('INSERT INTO categories (nama, slug, deskripsi) VALUES (?, ?, ?)', [
        form.nama,
        slug,
        form.deskripsi,
      ]);
      H.setFlash(res, 'Kategori baru ditambahkan.');
    } else {
      await pool.query('UPDATE categories SET nama = ?, slug = ?, deskripsi = ? WHERE id = ?', [
        form.nama,
        slug,
        form.deskripsi,
        id,
      ]);
      H.setFlash(res, 'Kategori diperbarui.');
    }

    return res.redirect('/seller/categories');
  } catch (error) {
    if (error.code === 'ER_DUP_ENTRY') {
      return await renderSellerCategories(res, {
        errors: { nama: 'Kategori dengan nama serupa sudah ada.' },
        form: blankCategoryForm(),
        status: 422,
      });
    }
    return next(error);
  }
});

app.get('/buyer/catalog', requireRole('buyer'), async (req, res, next) => {
  try {
    const [categoryRows] = await pool.query('SELECT nama FROM categories ORDER BY nama');
    const categoryOptions = categoryRows.map((row) => String(row.nama));

    const q = text(req.query.q);
    let desa = text(req.query.desa);
    let kategori = text(req.query.kategori);
    let sort = text(req.query.sort, 'unggulan');

    if (!DESA_OPTIONS.includes(desa)) desa = '';
    if (!categoryOptions.includes(kategori)) kategori = '';

    const sorts = {
      unggulan: 'p.unggulan DESC, p.nama ASC',
      murah: 'p.harga ASC, p.nama ASC',
      mahal: 'p.harga DESC, p.nama ASC',
      nama: 'p.nama ASC',
      stok: 'p.stok DESC, p.nama ASC',
    };
    if (!Object.prototype.hasOwnProperty.call(sorts, sort)) sort = 'unggulan';

    let sql =
      'SELECT p.*, s.nama_usaha, s.desa, s.whatsapp AS seller_whatsapp, c.nama AS kategori ' +
      'FROM products p JOIN sellers s ON s.id = p.seller_id ' +
      'JOIN categories c ON c.id = p.category_id WHERE 1 = 1';
    const params = [];

    if (q !== '') {
      sql += ' AND (p.nama LIKE ? OR c.nama LIKE ? OR p.deskripsi LIKE ? OR s.nama_usaha LIKE ? OR s.desa LIKE ?)';
      const like = `%${q}%`;
      params.push(like, like, like, like, like);
    }
    if (desa !== '') {
      sql += ' AND s.desa = ?';
      params.push(desa);
    }
    if (kategori !== '') {
      sql += ' AND c.nama = ?';
      params.push(kategori);
    }

    sql += ' ORDER BY ' + sorts[sort];

    const [products] = await pool.query(sql, params);
    const [[totals]] = await pool.query(
      `SELECT (SELECT COUNT(*) FROM products) AS total_products,
              (SELECT COUNT(*) FROM sellers) AS total_sellers`
    );

    return res.render('buyer/catalog', {
      pageTitle: 'Katalog Produk',
      products,
      categoryOptions,
      desaOptions: DESA_OPTIONS,
      filters: { q, desa, kategori, sort },
      totals,
      activeFilters: q !== '' || desa !== '' || kategori !== '' || sort !== 'unggulan',
    });
  } catch (error) {
    return next(error);
  }
});

app.use((req, res) => {
  res.status(404).send('Halaman tidak ditemukan.');
});

app.use((error, req, res, next) => {
  console.error(error);
  if (res.headersSent) return next(error);
  return res.status(500).send('Terjadi kesalahan pada server.');
});

if (require.main === module) {
  const port = Number(process.env.PORT || 3000);
  app.listen(port, () => {
    console.log(`Toko Osing berjalan di http://localhost:${port}`);
  });
}

module.exports = app;

