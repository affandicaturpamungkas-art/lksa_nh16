<?php
session_start();
include '../config/database.php';
// Set $sidebar_stats agar tidak ada error di header
$sidebar_stats = ''; 
include '../includes/header.php';

// Authorization check: Pimpinan, Kepala LKSA, dan Petugas Kotak Amal
if (!in_array($_SESSION['jabatan'] ?? '', ['Pimpinan', 'Kepala LKSA', 'Petugas Kotak Amal'])) {
    die("Akses ditolak.");
}

$id_kotak_amal = $_GET['id'] ?? '';
if (empty($id_kotak_amal)) {
    die("ID Kotak Amal tidak ditemukan.");
}

// Ambil data Kotak Amal
$sql = "SELECT * FROM KotakAmal WHERE ID_KotakAmal = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $id_kotak_amal);
$stmt->execute();
$result = $stmt->get_result();
$data_ka = $result->fetch_assoc();
$stmt->close();

if (!$data_ka) {
    die("Data Kotak Amal tidak ditemukan.");
}

$latitude = $data_ka['Latitude'] ?? 0;
$longitude = $data_ka['Longitude'] ?? 0;

// PERBAIKAN LINK PETA: Menggunakan format embed yang benar
// Jika Lat/Lng tidak 0, gunakan koordinat. Jika 0, fallback ke alamat toko.
if ($latitude != 0 && $longitude != 0) {
    // Format untuk embed Google Maps yang memungkinkan tampilan marker
    $map_link = "https://maps.google.com/maps?q={$latitude},{$longitude}&z=15&output=embed";
} else {
    // Jika koordinat 0.0, gunakan alamat toko untuk pencarian
    $encoded_address = urlencode($data_ka['Alamat_Toko'] ?? 'Lokasi Kotak Amal');
    $map_link = "https://maps.google.com/maps?q={$encoded_address}&z=15&output=embed";
}


$base_url = "http://" . $_SERVER['HTTP_HOST'] . "/lksa_nh/";
$foto_ka = $data_ka['Foto'] ?? '';
// Menggunakan gambar dari data, fallback ke yayasan.png
$foto_path = $foto_ka ? $base_url . 'assets/img/' . $foto_ka : $base_url . 'assets/img/yayasan.png'; 
?>
<style>
    /* Variabel Warna Elegan */
    :root {
        --ka-primary: #334155; /* Dark Slate - Teks utama */
        --ka-accent: #F97316; /* Orange - Aksen utama (Sesuai Kotak Amal) */
        --profile-bg: #FFFBEB; /* Very Light Cream - Background profil */
        --profile-border: #D97706; /* Muted Gold */
        --profile-shadow: rgba(249, 115, 22, 0.2);
        --btn-edit: #047857; /* Deep Emerald Green */
        /* Warna Aksen Data Row - Muted Deep Tones */
        --row-contact: #1E40AF; /* Deep Blue */
        --row-schedule: #A16207; /* Muted Gold/Brown */
        --row-location: #059669; /* Forest Green */
        --row-coordinate: #7C3AED; /* Deep Purple */
    }

    /* --- LAYOUT UTAMA (Perubahan untuk sejajar kiri) --- */
    .header-content-wrapper { 
        display: flex; 
        flex-direction: column; 
        align-items: flex-start; /* Mengatur konten sejajar kiri */
        width: 100%;
        margin-bottom: 20px;
    }
    
    /* PENYESUAIAN UKURAN JUDUL */
    .dashboard-title {
        font-size: 1.6em; 
        margin-bottom: 15px; /* Tambah jarak dengan kartu profil */
        font-weight: 800; /* Ditebalkan */
    }
    
    .detail-card {
        display: flex;
        gap: 30px; /* Ditingkatkan untuk ruang bernapas */
        flex-wrap: wrap;
        width: 100%;
    }
    
    /* KELOMPOK PROFIL & DATA KONTAK */
    .kotak-amal-header-profile { 
        display: flex; 
        flex-direction: column; 
        align-items: center; 
        gap: 5px; 
        margin-bottom: 0; 
        padding: 25px 30px; /* Padding disesuaikan */
        border-radius: 12px; /* Dibuat lebih kotak minimalis */
        background: var(--profile-bg); 
        border: 1px solid var(--profile-border); 
        box-shadow: 0 4px 15px rgba(0,0,0,0.05); /* Shadow lebih lembut */
        max-width: 300px; 
        text-align: center; 
        flex: 0 0 300px; 
        box-sizing: border-box;
        transition: transform 0.2s;
    }
    .kotak-amal-header-profile:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }
    
    .profile-info, .location-info {
        flex: 1 1 auto; /* Ambil sisa ruang */
        padding: 25px;
        border-radius: 12px; /* Dibuat lebih kotak minimalis */
        box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
        background-color: #FFFFFF; 
        border: 1px solid #E5E7EB; /* Border halus */
        min-width: 300px;
        box-sizing: border-box;
    }
    
    .profile-info {
        border-left: 6px solid var(--row-contact); /* Aksen warna biru tua */
    }

    .location-info {
        border-left: 6px solid var(--row-location); /* Aksen warna hijau */
        margin-top: 20px; 
        flex: 1 1 100%; /* Wajib 100% di baris kedua */
    }

    /* Penyesuaian Header Internal Card */
    .profile-info h2, .location-info h2 {
        color: var(--ka-primary);
        font-size: 1.4em;
        margin-top: 0;
        border-bottom: 2px solid #F3F4F6; /* Garis bawah lebih halus */
        padding-bottom: 8px;
        margin-bottom: 20px;
        font-weight: 700;
        font-family: 'Montserrat', sans-serif;
    }

    /* --- GAYA DATA ROW (Kompak dan Rapi) --- */
    .data-row {
        padding: 10px 0; 
        border-bottom: 1px dashed #F3F4F6; /* Garis putus-putus untuk visual separation */
    }
    .data-row:last-child {
        border-bottom: none;
    }
    .data-label-icon {
        color: #9CA3AF; 
        font-size: 0.95em; 
        width: 20px; 
    }
    .data-label {
        font-weight: 500; 
        color: #9CA3AF; 
        font-size: 0.85em; 
        text-transform: uppercase; 
        letter-spacing: 0.5px;
    }
    .data-value {
        font-weight: 600; 
        color: var(--ka-primary); 
        padding-left: 30px; 
        font-size: 0.95em; 
        line-height: 1.4;
        display: block;
    }
    
    .header-profile-img { 
        width: 100px; 
        height: 100px; 
        object-fit: cover;
        border-radius: 50%; 
        border: 4px solid #fff; 
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15); 
        flex-shrink: 0;
        margin-bottom: 15px; 
    }
    
    .map-frame {
        width: 100%;
        height: 300px; 
        border: 1px solid #ddd;
        border-radius: 8px; /* Dibuat lebih kecil */
        margin-top: 20px;
    }
    .coordinate-status {
        color: <?php echo ($latitude != 0 || $longitude != 0) ? 'var(--row-coordinate)' : 'var(--danger-color)'; ?>;
        font-weight: 600;
        display: block;
        margin-top: 5px;
        font-size: 0.9em;
    }
    
    /* Media Query untuk Mobile */
    @media (max-width: 768px) {
        .detail-card {
            flex-direction: column;
            gap: 20px;
        }
        .kotak-amal-header-profile, .profile-info, .location-info {
            flex: 1 1 100%;
            max-width: 100%; 
            min-width: 100%;
            padding: 20px;
            margin-top: 0 !important;
        }
    }
</style>

<div class="header-content-wrapper">
    <h1 class="dashboard-title" style="border-bottom: none; padding-bottom: 0;"><i class="fas fa-search-location" style="color: var(--ka-accent);"></i> Profil & Lokasi Kotak Amal</h1>
    
    <div class="kotak-amal-header-profile">
        <img src="<?php echo htmlspecialchars($foto_path); ?>" alt="Foto Kotak Amal" class="header-profile-img">
        <div class="header-profile-text">
            <p class="header-profile-name"><?php echo htmlspecialchars($data_ka['Nama_Toko']); ?></p>
            <small class="header-profile-id"><?php echo htmlspecialchars($data_ka['ID_KotakAmal']); ?> · LKSA <?php echo htmlspecialchars($data_ka['Id_lksa']); ?></small>
            
            <a href="edit_kotak_amal.php?id=<?php echo $data_ka['ID_KotakAmal']; ?>" class="btn btn-edit-profile-ka" title="Edit Data Kotak Amal">
                <i class="fas fa-edit"></i> Edit Data
            </a>
        </div>
    </div>
</div>


<div class="detail-card">
    
    <div class="profile-info">
        <h2 style="color: var(--row-contact);"><i class="fas fa-address-card"></i> Data Toko & Kontak</h2>
        
        <div> 
            
            <div class="data-row">
                <div class="data-label-group">
                    <i class="fas fa-user data-label-icon" style="color: var(--row-contact);"></i>
                    <span class="data-label">Nama Pemilik:</span>
                </div>
                <span class="data-value"><?php echo htmlspecialchars($data_ka['Nama_Pemilik'] ?? 'Belum Tercatat'); ?></span>
            </div>
            
            <div class="data-row">
                <div class="data-label-group">
                    <i class="fas fa-whatsapp data-label-icon" style="color: var(--row-contact);"></i>
                    <span class="data-label">Nomor WhatsApp:</span>
                </div>
                <span class="data-value"><?php echo htmlspecialchars($data_ka['WA_Pemilik'] ?? 'Belum Tercatat'); ?></span>
            </div>
            
            <div class="data-row">
                <div class="data-label-group">
                    <i class="fas fa-envelope data-label-icon" style="color: var(--row-contact);"></i>
                    <span class="data-label">Email:</span>
                </div>
                <span class="data-value"><?php echo htmlspecialchars($data_ka['Email'] ?? 'Belum Tercatat'); ?></span>
            </div>
            
            <div class="data-row">
                <div class="data-label-group">
                    <i class="fas fa-calendar-alt data-label-icon" style="color: var(--row-schedule);"></i>
                    <span class="data-label">Jadwal Pengambilan:</span>
                </div>
                <span class="data-value"><?php echo htmlspecialchars($data_ka['Jadwal_Pengambilan'] ?? 'Tidak Rutin'); ?></span>
            </div>
            
            <div class="data-row">
                <div class="data-label-group">
                    <i class="fas fa-sticky-note data-label-icon" style="color: #9CA3AF;"></i>
                    <span class="data-label">Keterangan Tambahan:</span>
                </div>
                <span class="data-value"><?php echo htmlspecialchars($data_ka['Ket'] ?? 'Tidak ada keterangan.'); ?></span>
            </div>
            
        </div> 
        
    </div>
    
    <div class="location-info">
        <h2 style="color: var(--row-location);"><i class="fas fa-map-marker-alt"></i> Lokasi & Peta</h2>
        
        <div class="data-row">
            <div class="data-label-group">
                <i class="fas fa-map-pin data-label-icon" style="color: var(--row-location);"></i>
                <span class="data-label">Alamat Lengkap:</span>
            </div>
            <span class="data-value"><?php echo htmlspecialchars($data_ka['Alamat_Toko'] ?? 'Koordinat Belum Dicatat'); ?></span>
        </div>
        
        <div class="data-row">
            <div class="data-label-group">
                <i class="fas fa-globe data-label-icon" style="color: var(--row-coordinate);"></i>
                <span class="data-label">Koordinat GPS:</span>
            </div>
            <span class="data-value">
                Lat: <?php echo htmlspecialchars($latitude); ?>, 
                Lng: <?php echo htmlspecialchars($longitude); ?>
            </span>
            <small class="coordinate-status">
                <?php echo ($latitude != 0 || $longitude != 0) ? 'Koordinat Tercatat.' : 'Koordinat Belum Ditetapkan/Masih 0.0.'; ?>
            </small>
        </div>
        
        <p style="margin-top: 25px; font-weight: 600; color: var(--ka-primary);">Tampilan Peta:</p>
        <iframe src="<?php echo $map_link; ?>" class="map-frame" allowfullscreen="" loading="lazy"></iframe>
        
        <a href="kotak-amal.php" class="btn btn-cancel" style="margin-top: 15px; width: 100%;"><i class="fas fa-arrow-left"></i> Kembali ke Manajemen Kotak Amal</a>
    </div>
</div>

<?php
include '../includes/footer.php';
$conn->close();
?>