'use strict';

const crypto = require('crypto');

const isProduction = process.env.NODE_ENV === 'production' || Boolean(process.env.VERCEL);
const APP_SECRET = process.env.APP_SECRET || 'dev-only-secret-change-me';

if (isProduction && !process.env.APP_SECRET) {
  throw new Error('APP_SECRET wajib diisi pada environment production.');
}

const AUTH_COOKIE = 'toko_auth';
const CSRF_COOKIE = 'toko_csrf';
const FLASH_COOKIE = 'toko_flash';
const SESSION_MS = 8 * 60 * 60 * 1000;

function esc(value) {
  return String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  }[character]));
}

function rupiah(value) {
  return 'Rp' + new Intl.NumberFormat('id-ID').format(Number(value || 0));
}

function roleLabel(role) {
  return role === 'admin' ? 'Admin' : role === 'seller' ? 'Seller' : 'Pembeli';
}

function homeFor(role) {
  if (role === 'admin') return '/admin/users';
  if (role === 'seller') return '/seller/products';
  return '/buyer/catalog';
}

function normalizePhone(value) {
  let digits = String(value ?? '').replace(/[\s\-().+]/g, '');
  if (!/^\d+$/.test(digits)) return '';
  if (digits.startsWith('0')) digits = '62' + digits.slice(1);
  return digits.length >= 9 && digits.length <= 15 ? digits : '';
}

function waUrl(phone, message) {
  return 'https://wa.me/' + normalizePhone(phone) + '?text=' + encodeURIComponent(message);
}

function categoryImage(category) {
  const map = {
    Batik: 'batik.svg',
    Kopi: 'kopi.svg',
    'Gula Aren': 'gula-aren.svg',
    Kerajinan: 'kerajinan.svg',
  };
  return '/assets/img/' + (map[category] || 'placeholder.svg');
}

function productImage(product) {
  const file = String(product.image_url || '').split(/[\\/]/).pop();
  if (file && /^[A-Za-z0-9._-]+$/.test(file)) {
    return '/assets/img/' + file;
  }
  return categoryImage(product.kategori);
}

function slugify(value) {
  return String(value)
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function cookieOptions(maxAge) {
  return {
    httpOnly: true,
    sameSite: 'lax',
    secure: isProduction,
    path: '/',
    ...(maxAge ? { maxAge } : {}),
  };
}

function sign(value) {
  return crypto.createHmac('sha256', APP_SECRET).update(value).digest('base64url');
}

function createSignedValue(value) {
  const encoded = Buffer.from(value, 'utf8').toString('base64url');
  return encoded + '.' + sign(encoded);
}

function verifySignedValue(raw) {
  if (!raw || typeof raw !== 'string') return null;
  const separator = raw.lastIndexOf('.');
  if (separator <= 0) return null;
  const encoded = raw.slice(0, separator);
  const signature = raw.slice(separator + 1);
  const expected = sign(encoded);
  if (signature.length !== expected.length) return null;
  if (!crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expected))) return null;
  try {
    return Buffer.from(encoded, 'base64url').toString('utf8');
  } catch {
    return null;
  }
}

function setAuthCookie(res, userId) {
  const payload = JSON.stringify({ uid: Number(userId), exp: Date.now() + SESSION_MS });
  res.cookie(AUTH_COOKIE, createSignedValue(payload), cookieOptions(SESSION_MS));
}

function clearAuthCookie(res) {
  res.clearCookie(AUTH_COOKIE, { path: '/' });
}

function authPayload(req) {
  const payload = verifySignedValue(req.cookies?.[AUTH_COOKIE]);
  if (!payload) return null;
  try {
    const data = JSON.parse(payload);
    if (!Number.isInteger(data.uid) || !Number.isFinite(data.exp) || data.exp < Date.now()) {
      return null;
    }
    return data;
  } catch {
    return null;
  }
}

function setFlash(res, message, type = 'success') {
  res.cookie(FLASH_COOKIE, createSignedValue(JSON.stringify({ message, type })), cookieOptions(60000));
}

function flashMiddleware(req, res, next) {
  const raw = req.cookies?.[FLASH_COOKIE];
  if (raw) {
    const value = verifySignedValue(raw);
    if (value) {
      try {
        res.locals.flash = JSON.parse(value);
      } catch {
        res.locals.flash = null;
      }
    }
    res.clearCookie(FLASH_COOKIE, { path: '/' });
  }
  next();
}

function csrfMiddleware(req, res, next) {
  let token = req.cookies?.[CSRF_COOKIE];
  if (!token || !/^[a-f0-9]{64}$/.test(token)) {
    token = crypto.randomBytes(32).toString('hex');
    res.cookie(CSRF_COOKIE, token, cookieOptions());
  }
  res.locals.csrfToken = token;
  next();
}

function csrfCheck(req, res, next) {
  const cookieToken = req.cookies?.[CSRF_COOKIE] || '';
  const formToken = typeof req.body?._csrf === 'string' ? req.body._csrf : '';
  const valid =
    cookieToken.length > 0 &&
    cookieToken.length === formToken.length &&
    crypto.timingSafeEqual(Buffer.from(cookieToken), Buffer.from(formToken));

  if (!valid) {
    res.status(419).send('Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    return;
  }
  next();
}

module.exports = {
  esc,
  rupiah,
  roleLabel,
  homeFor,
  normalizePhone,
  waUrl,
  categoryImage,
  productImage,
  slugify,
  authPayload,
  setAuthCookie,
  clearAuthCookie,
  setFlash,
  flashMiddleware,
  csrfMiddleware,
  csrfCheck,
};

