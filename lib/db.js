'use strict';

require('dotenv').config({ quiet: true });

const mysql = require('mysql2/promise');

const pool = mysql.createPool({
  host: process.env.DB_HOST || '127.0.0.1',
  port: Number(process.env.DB_PORT || 3306),
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'toko_osing_db',
  waitForConnections: true,
  connectionLimit: Number(process.env.DB_POOL || 5),
  maxIdle: Number(process.env.DB_POOL || 5),
  idleTimeout: 60000,
  queueLimit: 0,
  dateStrings: true,
  ssl: process.env.DB_SSL === 'true' ? { rejectUnauthorized: false } : undefined,
});

module.exports = pool;

