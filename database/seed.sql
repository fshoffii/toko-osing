USE toko_osing_db;

INSERT INTO sellers (id, nama_usaha, desa, profil, whatsapp) VALUES
  (1, 'Sanggar Batik Kemiren Asri', 'Kemiren', 'Usaha batik yang membuat kain dan selendang dengan motif tradisional.', '6280000000001'),
  (2, 'Griya Kopi Kemiren', 'Kemiren', 'Usaha pengolahan kopi bubuk dari biji hasil kebun sekitar desa.', '6280000000002'),
  (3, 'Kopi Rakyat Tamansari', 'Tamansari', 'Usaha sangrai biji kopi robusta dalam kemasan rumahan.', '6280000000003'),
  (4, 'Gula Aren Sumber Lestari', 'Tamansari', 'Usaha pengolah nira aren menjadi gula cetak dan gula semut.', '6280000000004'),
  (5, 'Anyaman Bambu Osing Karya', 'Osing', 'Usaha kerajinan anyaman bambu dan pandan buatan tangan.', '6280000000005'),
  (6, 'Kriya Kayu Osing Jaya', 'Osing', 'Usaha kerajinan kayu ukir berupa suvenir dan hiasan.', '6280000000006');

INSERT INTO users (id, nama, email, password_hash, role, whatsapp, seller_id, is_active) VALUES
  (1, 'Admin Toko Osing', 'admin@tokoosing.test', '$2b$12$6Njo1weZ.mszYkbHZdJwQ.CyRtNBqKQd0A3p18OR6G8/zCB2hU.lW', 'admin', NULL, NULL, 1),
  (2, 'Pembeli Contoh', 'buyer@tokoosing.test', '$2b$12$2Zu5q2tqsx6g3m3Raxm46eKRFY5692HqVbxRBayWisF8R38k4RQqm', 'buyer', '6280000000099', NULL, 1),
  (3, 'Seller Batik Kemiren', 'batik.kemiren@tokoosing.test', '$2b$12$MGS.bvnqiIUntUqfRd1iEuoZQLRaJ9wJQl62EDNnEAD/rFEVi.eZ2', 'seller', '6280000000001', 1, 1),
  (4, 'Seller Kopi Kemiren', 'kopi.kemiren@tokoosing.test', '$2b$12$MGS.bvnqiIUntUqfRd1iEuoZQLRaJ9wJQl62EDNnEAD/rFEVi.eZ2', 'seller', '6280000000002', 2, 1),
  (5, 'Seller Kopi Tamansari', 'kopi.tamansari@tokoosing.test', '$2b$12$MGS.bvnqiIUntUqfRd1iEuoZQLRaJ9wJQl62EDNnEAD/rFEVi.eZ2', 'seller', '6280000000003', 3, 1),
  (6, 'Seller Gula Aren', 'gula.tamansari@tokoosing.test', '$2b$12$MGS.bvnqiIUntUqfRd1iEuoZQLRaJ9wJQl62EDNnEAD/rFEVi.eZ2', 'seller', '6280000000004', 4, 1),
  (7, 'Seller Anyaman Osing', 'anyaman.osing@tokoosing.test', '$2b$12$MGS.bvnqiIUntUqfRd1iEuoZQLRaJ9wJQl62EDNnEAD/rFEVi.eZ2', 'seller', '6280000000005', 5, 1),
  (8, 'Seller Kayu Osing', 'kayu.osing@tokoosing.test', '$2b$12$MGS.bvnqiIUntUqfRd1iEuoZQLRaJ9wJQl62EDNnEAD/rFEVi.eZ2', 'seller', '6280000000006', 6, 1);

INSERT INTO categories (id, nama, slug, deskripsi) VALUES
  (1, 'Batik', 'batik', 'Kain dan selendang batik tulis maupun cap.'),
  (2, 'Kopi', 'kopi', 'Kopi bubuk dan biji sangrai dari kebun desa.'),
  (3, 'Gula Aren', 'gula-aren', 'Gula cetak dan gula semut dari nira aren.'),
  (4, 'Kerajinan', 'kerajinan', 'Anyaman, ukiran kayu, dan suvenir tangan.');

INSERT INTO products (id, seller_id, category_id, nama, harga, stok, deskripsi, image_url, unggulan, oleh_oleh) VALUES
  (1, 1, 1, 'Batik Tulis Motif Gajah Oling', 350000, 6, 'Kain batik tulis dengan motif tradisional gajah oling, ukuran sekitar 2 meter.', '', 1, 1),
  (2, 1, 1, 'Selendang Batik Cap Motif Kopi Pecah', 120000, 14, 'Selendang batik cap bermotif biji kopi pecah untuk pelengkap busana harian.', '', 0, 1),
  (3, 2, 2, 'Kopi Osing Bubuk 200 g', 45000, 40, 'Kopi bubuk sangrai sedang, beraroma kuat dan cocok diseduh tubruk.', '', 1, 1),
  (4, 3, 2, 'Kopi Biji Sangrai Robusta 250 g', 55000, 22, 'Biji kopi robusta sangrai utuh yang bisa digiling sendiri di rumah.', '', 0, 1),
  (5, 4, 3, 'Gula Aren Cetak 500 g', 30000, 50, 'Gula aren cetak dari nira aren, cocok untuk minuman dan kue tradisional.', '', 1, 1),
  (6, 4, 3, 'Gula Aren Semut 250 g', 22000, 0, 'Gula aren butiran halus yang mudah larut untuk kopi dan teh.', '', 0, 1),
  (7, 5, 4, 'Anyaman Bambu Tempat Tisu', 35000, 18, 'Tempat tisu anyaman bambu buatan tangan dengan finishing halus.', '', 0, 0),
  (8, 5, 4, 'Tas Anyaman Pandan', 85000, 9, 'Tas anyaman daun pandan yang ringan untuk belanja dan jalan-jalan.', '', 0, 0),
  (9, 6, 4, 'Gantungan Kunci Kayu Ukir', 15000, 60, 'Gantungan kunci kayu berukir sederhana, cocok sebagai suvenir.', '', 0, 1),
  (10, 6, 4, 'Topeng Kayu Hias Mini', 90000, 5, 'Topeng kayu berukuran mini untuk hiasan dinding atau meja.', '', 0, 1);


