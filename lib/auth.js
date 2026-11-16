'use strict';

const pool = require('./db');
const { authPayload, clearAuthCookie, setFlash } = require('./helpers');

const USER_QUERY =
  'SELECT u.*, s.nama_usaha, s.desa, s.profil, s.whatsapp AS seller_whatsapp ' +
  'FROM users u LEFT JOIN sellers s ON s.id = u.seller_id WHERE u.id = ? LIMIT 1';

async function attachUser(req, res, next) {
  try {
    const payload = authPayload(req);
    if (!payload) {
      req.user = null;
      res.locals.currentUser = null;
      return next();
    }

    const [rows] = await pool.query(USER_QUERY, [payload.uid]);
    const user = rows[0];

    if (!user || !Number(user.is_active)) {
      req.user = null;
      res.locals.currentUser = null;
      clearAuthCookie(res);
      return next();
    }

    req.user = user;
    res.locals.currentUser = user;
    next();
  } catch (error) {
    next(error);
  }
}

function requireRole(...roles) {
  return (req, res, next) => {
    if (!req.user) {
      setFlash(res, 'Silakan masuk terlebih dahulu.', 'error');
      return res.redirect('/login');
    }

    if (!roles.includes(req.user.role)) {
      return res.status(403).send('Akses ditolak. Peran akun Anda tidak diizinkan membuka halaman ini.');
    }

    next();
  };
}

module.exports = { attachUser, requireRole, USER_QUERY };

