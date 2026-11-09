import { formatAngka, filterPidatoByKategori, hitungRataRataIHSG } from './utils.js';
import { inisialisasiFormValidasi } from './form-validasi.js';

const endpointFrankfurter = 'https://api.frankfurter.dev/v2';
const mataUangUtama = ['USD', 'EUR', 'JPY', 'GBP', 'SGD', 'AUD', 'CNY'];
const intervalCekSiaran = 90000;
const dataPidato = [
    { id: 1, judul: 'Pidato RUU APBN 2027', tanggal: '2026-08-16', kategori: 'Ekonomi', dampakIHSG: 0.8, statusPasar: 'Buka' },
    { id: 2, judul: 'Pidato Kenegaraan HUT RI', tanggal: '2026-08-17', kategori: 'Geopolitik', dampakIHSG: null, statusPasar: 'Libur' }
];

function inisialisasiAplikasi() {
    try {
        console.log('=== MEMATANGKAN DATA PIDATOKU ===');

        const pidatoEkonomi = filterPidatoByKategori(dataPidato, 'Ekonomi');
        console.log('Hasil Filter Pidato Ekonomi:', pidatoEkonomi);

        const rataIHSG = hitungRataRataIHSG(dataPidato);
        console.log(`Rata-rata Dampak IHSG: +${rataIHSG}%`);
    } catch (error) {
        console.warn('Errort terdeteksi:', error.message);
    }
}

const svgNS = 'http://www.w3.org/2000/svg';
const intervalCekIHSG = 300000;

function buatElemenSvg(nama, atribut = {}) {
    const elemen = document.createElementNS(svgNS, nama);
    Object.entries(atribut).forEach(([kunci, nilai]) => elemen.setAttribute(kunci, String(nilai)));
    return elemen;
}

function gambarGrafikIHSG(wadah, riwayat) {
    const lebar = wadah.clientWidth;
    const tinggi = wadah.clientHeight;
    if (riwayat.length < 2 || lebar === 0 || tinggi === 0) return false;

    const tepi = { kiri: 12, kanan: 64, atas: 16, bawah: 28 };
    const daftarNilai = riwayat.map(titik => titik.tutup);
    const terendah = Math.min(...daftarNilai);
    const tertinggi = Math.max(...daftarNilai);
    const rentang = tertinggi - terendah || 1;
    const batasBawah = terendah - rentang * 0.08;
    const batasAtas = tertinggi + rentang * 0.08;
    const lebarPlot = lebar - tepi.kiri - tepi.kanan;
    const tinggiPlot = tinggi - tepi.atas - tepi.bawah;
    const posisiX = indeks => tepi.kiri + (indeks * lebarPlot) / (riwayat.length - 1);
    const posisiY = nilai => tepi.atas + ((batasAtas - nilai) / (batasAtas - batasBawah)) * tinggiPlot;
    const naik = daftarNilai[daftarNilai.length - 1] >= daftarNilai[0];
    const warna = naik ? '#38c982' : '#f87171';
    const formatTanggal = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', timeZone: 'Asia/Jakarta' });

    const svg = buatElemenSvg('svg', { class: 'ihsg-svg', viewBox: `0 0 ${lebar} ${tinggi}`, 'aria-hidden': 'true' });
    const defs = buatElemenSvg('defs');
    const gradien = buatElemenSvg('linearGradient', { id: 'ihsg-area', x1: 0, y1: 0, x2: 0, y2: 1 });
    gradien.append(
        buatElemenSvg('stop', { offset: '0%', 'stop-color': warna, 'stop-opacity': 0.25 }),
        buatElemenSvg('stop', { offset: '100%', 'stop-color': warna, 'stop-opacity': 0 })
    );
    defs.append(gradien);
    svg.append(defs);

    for (let langkah = 0; langkah < 4; langkah++) {
        const nilai = batasBawah + ((batasAtas - batasBawah) * (langkah + 0.5)) / 4;
        const y = posisiY(nilai);
        svg.append(buatElemenSvg('line', { class: 'ihsg-grid', x1: tepi.kiri, y1: y, x2: lebar - tepi.kanan, y2: y }));
        const label = buatElemenSvg('text', { class: 'ihsg-label', x: lebar - tepi.kanan + 8, y: y + 4 });
        label.textContent = Math.round(nilai).toLocaleString('id-ID');
        svg.append(label);
    }

    const indeksTerakhir = riwayat.length - 1;
    const indeksLabel = [...new Set([0, Math.round(indeksTerakhir / 3), Math.round((indeksTerakhir * 2) / 3), indeksTerakhir])];
    indeksLabel.forEach((indeks, urutan) => {
        const posisiTeks = urutan === 0 ? 'start' : urutan === indeksLabel.length - 1 ? 'end' : 'middle';
        const label = buatElemenSvg('text', { class: 'ihsg-label', x: posisiX(indeks), y: tinggi - 9, 'text-anchor': posisiTeks });
        label.textContent = formatTanggal.format(new Date(`${riwayat[indeks].tanggal}T12:00:00+07:00`));
        svg.append(label);
    });

    const titik = riwayat.map((item, indeks) => `${posisiX(indeks).toFixed(1)},${posisiY(item.tutup).toFixed(1)}`);
    const dasar = (tepi.atas + tinggiPlot).toFixed(1);
    svg.append(
        buatElemenSvg('path', {
            d: `M${titik.join(' L')} L${posisiX(indeksTerakhir).toFixed(1)},${dasar} L${posisiX(0).toFixed(1)},${dasar} Z`,
            fill: 'url(#ihsg-area)'
        }),
        buatElemenSvg('polyline', { class: 'ihsg-line', points: titik.join(' '), stroke: warna }),
        buatElemenSvg('circle', { cx: posisiX(indeksTerakhir), cy: posisiY(daftarNilai[indeksTerakhir]), r: 3.5, fill: warna })
    );

    wadah.replaceChildren(svg);
    return true;
}

function inisialisasiIHSG() {
    const elemen = {
        nilai: document.getElementById('ihsg-value'),
        perubahan: document.getElementById('ihsg-change'),
        grafik: document.getElementById('ihsg-chart'),
        sumber: document.getElementById('ihsg-source'),
        tombolRetry: document.getElementById('ihsg-retry-button')
    };
    if (!elemen.grafik) return;

    let riwayatTerakhir = [];

    function tampilkanStatusGrafik(pesan) {
        const status = document.createElement('p');
        status.className = 'chart-status';
        status.textContent = pesan;
        elemen.grafik.replaceChildren(status);
    }

    function tampilkanDataIHSG(data) {
        elemen.nilai.textContent = formatAngka(data.harga);

        if (typeof data.perubahan === 'number') {
            const naik = data.perubahan >= 0;
            const tanda = naik ? '+' : '';
            const persen = typeof data.persen === 'number' ? ` (${tanda}${formatAngka(data.persen)}%)` : '';
            elemen.perubahan.textContent = `${tanda}${formatAngka(data.perubahan)}${persen} hari ini`;
            elemen.perubahan.classList.toggle('naik', naik);
            elemen.perubahan.classList.toggle('turun', !naik);
        } else {
            elemen.perubahan.textContent = 'Perubahan harian: -';
            elemen.perubahan.classList.remove('naik', 'turun');
        }

        riwayatTerakhir = Array.isArray(data.riwayat) ? data.riwayat : [];
        if (!gambarGrafikIHSG(elemen.grafik, riwayatTerakhir)) {
            tampilkanStatusGrafik('Riwayat harga IHSG belum tersedia di Google Sheet.');
        }

        const waktu = data.waktu_transaksi
            ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' }).format(new Date(data.waktu_transaksi))
            : 'tidak diketahui';
        elemen.sumber.textContent = `Sumber: ${data.sumber} melalui Google Sheets, transaksi terakhir ${waktu} WIB. Data dapat tertunda hingga ${data.tertunda_menit} menit.`;
        elemen.tombolRetry.hidden = true;
    }

    async function muatDataIHSG() {
        if (riwayatTerakhir.length === 0) tampilkanStatusGrafik('Memuat grafik IHSG...');
        elemen.tombolRetry.hidden = true;

        try {
            const respons = await fetch('./api/ihsg.php');
            const hasil = await respons.json().catch(() => null);
            if (!respons.ok || !hasil || hasil.status !== 'success') {
                throw new Error(hasil?.pesan ?? `HTTP ${respons.status}`);
            }
            tampilkanDataIHSG(hasil.data);
        } catch (error) {
            console.warn('Gagal memuat data IHSG:', error.message);
            if (riwayatTerakhir.length > 0) return;

            elemen.nilai.textContent = 'IHSG tidak tersedia';
            elemen.perubahan.textContent = 'Perubahan harian: -';
            elemen.perubahan.classList.remove('naik', 'turun');
            tampilkanStatusGrafik('Grafik IHSG gagal dimuat.');
            elemen.sumber.textContent = `Gagal memuat data IHSG: ${error.message}`;
            elemen.tombolRetry.hidden = false;
        }
    }

    elemen.tombolRetry.addEventListener('click', muatDataIHSG);

    if ('ResizeObserver' in window) {
        new ResizeObserver(() => {
            if (riwayatTerakhir.length > 1) gambarGrafikIHSG(elemen.grafik, riwayatTerakhir);
        }).observe(elemen.grafik);
    }

    setInterval(() => {
        if (!document.hidden) muatDataIHSG();
    }, intervalCekIHSG);

    muatDataIHSG();
}

function inisialisasiPemilihMataUang(elemenMataUang) {
    const state = {
        daftarMataUang: [],
        mataUangTerpilih: 'USD',
        menuTerbuka: false
    };

    const storageKey = 'pidatoku.selectedCurrency';

    function bacaMataUangTersimpan() {
        try {
            const tersimpan = localStorage.getItem(storageKey);
            if (tersimpan && /^[A-Z]{3}$/.test(tersimpan)) {
                state.mataUangTerpilih = tersimpan;
            }
        } catch (error) {
            console.warn('Penyimpanan lokal tidak tersedia:', error.message);
        }
    }

    function simpanMataUangTerpilih() {
        try {
            localStorage.setItem(storageKey, state.mataUangTerpilih);
        } catch (error) {
            console.warn('Gagal menyimpan preferensi mata uang:', error.message);
        }
    }

    function aturMenu(terbuka) {
        state.menuTerbuka = terbuka;
        elemenMataUang.menu.hidden = !terbuka;
        elemenMataUang.tombol.setAttribute('aria-expanded', String(terbuka));

        if (terbuka) {
            elemenMataUang.pencarian.value = '';
            tampilkanPilihan('');
            elemenMataUang.pencarian.focus();
        }
    }

    function tampilkanPilihan(kataKunci) {
        const kata = kataKunci.trim().toLocaleLowerCase('id-ID');
        const hasil = state.daftarMataUang.filter(mataUang =>
            mataUang.iso_code !== 'IDR' &&
            /^[A-Z]{3}$/.test(mataUang.iso_code) &&
            `${mataUang.iso_code} ${mataUang.name}`.toLocaleLowerCase('id-ID').includes(kata)
        );

        elemenMataUang.pilihan.replaceChildren();

        if (hasil.length === 0) {
            const kosong = document.createElement('p');
            kosong.className = 'currency-empty';
            kosong.textContent = 'Mata uang tidak ditemukan.';
            elemenMataUang.pilihan.append(kosong);
            return;
        }

        hasil.sort((mataUangA, mataUangB) => {
            const urutanA = mataUangUtama.indexOf(mataUangA.iso_code);
            const urutanB = mataUangUtama.indexOf(mataUangB.iso_code);
            const nilaiA = urutanA === -1 ? mataUangUtama.length : urutanA;
            const nilaiB = urutanB === -1 ? mataUangUtama.length : urutanB;
            return nilaiA - nilaiB || mataUangA.name.localeCompare(mataUangB.name, 'id-ID');
        }).forEach(mataUang => {
            const pilihan = document.createElement('button');
            pilihan.className = 'currency-option';
            pilihan.type = 'button';
            pilihan.setAttribute('role', 'option');
            pilihan.setAttribute('aria-selected', String(mataUang.iso_code === state.mataUangTerpilih));
            pilihan.dataset.code = mataUang.iso_code;

            const kode = document.createElement('span');
            kode.className = 'currency-option-code';
            kode.textContent = mataUang.iso_code;

            const nama = document.createElement('span');
            nama.className = 'currency-option-name';
            nama.textContent = mataUang.name;

            pilihan.append(kode, nama);
            elemenMataUang.pilihan.append(pilihan);
        });
    }

    async function muatKurs(kodeMataUang) {
        elemenMataUang.nilai.textContent = 'Memuat kurs...';
        elemenMataUang.pasangan.textContent = `1 ${kodeMataUang} = ... IDR`;
        elemenMataUang.status.textContent = 'Mengambil kurs referensi harian...';
        if (elemenMataUang.tombolRetry) elemenMataUang.tombolRetry.hidden = true;

        try {
            const respons = await fetch(`${endpointFrankfurter}/rate/${kodeMataUang}/IDR`);
            if (!respons.ok) throw new Error(`HTTP ${respons.status}`);

            const data = await respons.json();
            tampilkanDataKurs(data, 'Frankfurter');
        } catch (error) {
            console.warn('Gagal memuat kurs dari API publik:', error.message);

            try {
                const responsLokal = await fetch('./data/rates-fallback.json');
                if (!responsLokal.ok) throw new Error(`HTTP ${responsLokal.status}`);
                const dataLokal = await responsLokal.json();

                if (dataLokal[kodeMataUang]) {
                    tampilkanDataKurs(dataLokal[kodeMataUang], 'Cadangan Lokal');
                    return;
                }
            } catch (errFallback) {
                console.warn('Data cadangan lokal tidak tersedia:', errFallback.message);
            }

            elemenMataUang.nilai.textContent = 'Kurs tidak tersedia';
            elemenMataUang.pasangan.textContent = `${kodeMataUang}/IDR`;
            elemenMataUang.status.textContent = 'Gagal memuat kurs. Periksa koneksi internet atau coba lagi.';
            if (elemenMataUang.tombolRetry) elemenMataUang.tombolRetry.hidden = false;
        }
    }

    function tampilkanDataKurs(data, sumberLabel) {
        const nilaiKurs = new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0
        }).format(data.rate);

        const tanggal = new Intl.DateTimeFormat('id-ID', {
            dateStyle: 'medium',
            timeZone: 'UTC'
        }).format(new Date(`${data.date}T12:00:00Z`));

        elemenMataUang.nilai.textContent = nilaiKurs;
        elemenMataUang.pasangan.textContent = `1 ${data.base} = ${nilaiKurs}`;
        elemenMataUang.status.textContent = `Kurs acuan harian, ${tanggal}. Sumber: ${sumberLabel}.`;
        if (elemenMataUang.tombolRetry) elemenMataUang.tombolRetry.hidden = true;
    }

    async function muatDaftarMataUang() {
        elemenMataUang.pilihan.textContent = 'Memuat daftar mata uang...';

        try {
            const respons = await fetch(`${endpointFrankfurter}/currencies`);
            if (!respons.ok) throw new Error(`HTTP ${respons.status}`);

            state.daftarMataUang = await respons.json();
            tampilkanPilihan(elemenMataUang.pencarian.value);
        } catch (error) {
            console.warn('Gagal memuat daftar mata uang dari API:', error.message);

            try {
                const responsLokal = await fetch('./data/currencies.json');
                if (responsLokal.ok) {
                    state.daftarMataUang = await responsLokal.json();
                    tampilkanPilihan(elemenMataUang.pencarian.value);
                    return;
                }
            } catch (errLokal) {
                console.warn('Fallback mata uang lokal gagal:', errLokal.message);
            }

            elemenMataUang.pilihan.textContent = 'Daftar mata uang gagal dimuat.';
        }
    }

    elemenMataUang.tombol.addEventListener('click', () => {
        aturMenu(elemenMataUang.menu.hidden);
    });

    elemenMataUang.pencarian.addEventListener('input', peristiwa => {
        tampilkanPilihan(peristiwa.target.value);
    });

    elemenMataUang.pencarian.addEventListener('keydown', peristiwa => {
        if (peristiwa.key === 'ArrowDown') {
            peristiwa.preventDefault();
            const pilihanPertama = elemenMataUang.pilihan.querySelector('.currency-option');
            if (pilihanPertama) pilihanPertama.focus();
        }
        if (peristiwa.key === 'Escape') {
            aturMenu(false);
            elemenMataUang.tombol.focus();
        }
    });

    elemenMataUang.pilihan.addEventListener('click', peristiwa => {
        const pilihan = peristiwa.target.closest('.currency-option');
        if (!pilihan) return;

        state.mataUangTerpilih = pilihan.dataset.code;
        simpanMataUangTerpilih();
        aturMenu(false);
        muatKurs(state.mataUangTerpilih);
    });

    elemenMataUang.pilihan.addEventListener('keydown', peristiwa => {
        const pilihanTersedia = [...elemenMataUang.pilihan.querySelectorAll('.currency-option')];
        const indeks = pilihanTersedia.indexOf(document.activeElement);
        if (pilihanTersedia.length === 0) return;

        if (peristiwa.key === 'ArrowDown' || peristiwa.key === 'ArrowUp') {
            peristiwa.preventDefault();
            const arah = peristiwa.key === 'ArrowDown' ? 1 : -1;
            const indeksBerikutnya = (indeks + arah + pilihanTersedia.length) % pilihanTersedia.length;
            pilihanTersedia[indeksBerikutnya].focus();
        }
        if (peristiwa.key === 'Escape') {
            aturMenu(false);
            elemenMataUang.tombol.focus();
        }
    });

    document.addEventListener('click', peristiwa => {
        if (!peristiwa.target.closest('.currency-picker')) aturMenu(false);
    });

    if (elemenMataUang.tombolRetry) {
        elemenMataUang.tombolRetry.addEventListener('click', () => {
            muatKurs(state.mataUangTerpilih);
        });
    }

    bacaMataUangTersimpan();
    muatKurs(state.mataUangTerpilih);
    muatDaftarMataUang();
}

function inisialisasiPasar() {
    const elemenMataUang = {
        tombol: document.getElementById('currency-picker-button'),
        menu: document.getElementById('currency-picker-menu'),
        pencarian: document.getElementById('currency-search'),
        pilihan: document.getElementById('currency-options'),
        nilai: document.getElementById('fx-rate-value'),
        pasangan: document.getElementById('fx-pair-label'),
        status: document.getElementById('fx-rate-meta'),
        tombolRetry: document.getElementById('fx-retry-button')
    };

    inisialisasiIHSG();
    inisialisasiPemilihMataUang(elemenMataUang);
}

function inisialisasiPlayerSiaran() {
    const kontainer = document.querySelector('.stream-container');
    const tombolPlay = document.getElementById('btn-play-stream');
    const tombolPindah = document.getElementById('btn-switch-stream');
    const elemenSiaran = {
        tickerBadge: document.getElementById('ticker-badge'),
        badge: document.getElementById('stream-badge'),
        judul: document.getElementById('stream-title'),
        pratinjau: document.getElementById('stream-preview'),
        sumber: document.getElementById('stream-source'),
        tanggal: document.getElementById('stream-date')
    };
    if (!kontainer) return;

    kontainer.style.cursor = 'pointer';
    kontainer.setAttribute('role', 'button');
    kontainer.setAttribute('tabindex', '0');
    kontainer.setAttribute('aria-label', 'Putar siaran pidato kenegaraan');

    let infoSiaran = null;
    let videoDiputar = null;
    let sedangMemuat = false;

    function tampilkanInfoSiaran(data) {
        const formatTanggal = new Intl.DateTimeFormat('id-ID', {
            dateStyle: 'long',
            timeZone: 'Asia/Jakarta'
        });

        elemenSiaran.tickerBadge.textContent = data.badge;
        elemenSiaran.badge.textContent = data.badge;
        elemenSiaran.badge.classList.toggle('live', data.is_live);
        elemenSiaran.badge.classList.toggle('realtime', !data.is_live);
        elemenSiaran.judul.textContent = data.judul;
        elemenSiaran.sumber.textContent = data.sumber;
        elemenSiaran.tanggal.dateTime = data.tayang_pada;
        elemenSiaran.tanggal.textContent = formatTanggal.format(new Date(data.tayang_pada));

        if (!videoDiputar) {
            tombolPlay.textContent = data.is_live ? 'Putar Siaran Langsung' : 'Putar Pidato Terbaru';
            if (data.thumbnail) {
                elemenSiaran.pratinjau.src = data.thumbnail;
                elemenSiaran.pratinjau.alt = data.judul;
            }
        }
    }

    async function muatInfoSiaran() {
        try {
            const respons = await fetch('./api/live-stream.php');
            if (!respons.ok) throw new Error(`HTTP ${respons.status}`);

            const hasil = await respons.json();
            infoSiaran = hasil.data;

            if (!videoDiputar) {
                tampilkanInfoSiaran(infoSiaran);
            } else {
                tombolPindah.hidden = !(infoSiaran.is_live && infoSiaran.video_id !== videoDiputar);
            }
        } catch (error) {
            console.warn('Gagal memuat data API siaran:', error.message);
        }
    }

    function tampilkanVideo(data) {
        const pemutar = document.createElement('iframe');
        pemutar.src = `${data.url_video}?autoplay=1`;
        pemutar.title = data.judul;
        pemutar.className = 'stream-frame';
        pemutar.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
        pemutar.allowFullscreen = true;

        videoDiputar = data.video_id;
        kontainer.replaceChildren(pemutar);
        kontainer.style.cursor = '';
        kontainer.removeAttribute('role');
        kontainer.removeAttribute('tabindex');
        kontainer.removeAttribute('aria-label');

        tampilkanInfoSiaran(data);
        tombolPlay.textContent = 'Siaran Sedang Diputar';
        tombolPlay.disabled = true;
        tombolPindah.hidden = true;
    }

    async function putarSiaran() {
        if (sedangMemuat || videoDiputar) return;
        sedangMemuat = true;

        if (!infoSiaran) await muatInfoSiaran();

        if (infoSiaran) {
            tampilkanVideo(infoSiaran);
        } else {
            tombolPlay.textContent = 'Gagal Memuat, Coba Lagi';
        }

        sedangMemuat = false;
    }

    kontainer.addEventListener('click', putarSiaran);
    tombolPlay.addEventListener('click', putarSiaran);
    tombolPindah.addEventListener('click', () => tampilkanVideo(infoSiaran));
    kontainer.addEventListener('keydown', (peristiwa) => {
        if (peristiwa.key === 'Enter' || peristiwa.key === ' ') {
            peristiwa.preventDefault();
            putarSiaran();
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) muatInfoSiaran();
    });
    setInterval(() => {
        if (!document.hidden) muatInfoSiaran();
    }, intervalCekSiaran);

    muatInfoSiaran();
}

document.addEventListener('DOMContentLoaded', () => {
    inisialisasiAplikasi();
    inisialisasiPasar();
    inisialisasiPlayerSiaran();
    inisialisasiFormValidasi();
});