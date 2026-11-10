import {
    endpointPidato,
    formatTanggal,
    formatDurasi,
    teksDampakKurs,
    teksPersen,
    kelasArah,
    buatElemen,
    buatKartuPidato,
    buatKartuMemuat,
    tampilkanPagination,
    panggilApi,
    sinkronOtomatis
} from './arsip.js';
import { gambarGrafikIHSG } from './grafik-ihsg.js';

const endpointPasar = './api/pasar.php';
const jumlahPerHalaman = 9;
const daftarUrutan = ['terbaru', 'terlama', 'ihsg_tertinggi', 'ihsg_terendah'];
const formatBulanTahun = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' });
const formatTanggalSingkat = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
const formatIndeks = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const formatRupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });

const elemen = {
    statTotal: document.getElementById('stat-total'),
    statSejak: document.getElementById('stat-sejak'),
    statRata: document.getElementById('stat-rata'),
    statRataCatatan: document.getElementById('stat-rata-catatan'),
    statTerbesar: document.getElementById('stat-terbesar'),
    statTerbesarCatatan: document.getElementById('stat-terbesar-catatan'),
    pemutar: document.getElementById('pemutar-arsip'),
    labelPemutar: document.getElementById('unggulan-label'),
    badge: document.getElementById('arsip-badge'),
    judul: document.getElementById('arsip-judul'),
    video: document.getElementById('arsip-video'),
    sumber: document.getElementById('arsip-sumber'),
    tanggal: document.getElementById('arsip-tanggal'),
    durasi: document.getElementById('arsip-durasi'),
    deskripsi: document.getElementById('arsip-deskripsi'),
    tombolPutar: document.getElementById('arsip-tombol-putar'),
    judulPasar: document.getElementById('pasar-judul'),
    ihsgNilai: document.getElementById('pasar-ihsg-nilai'),
    ihsgPerubahan: document.getElementById('pasar-ihsg-perubahan'),
    grafik: document.getElementById('pasar-grafik'),
    kursNilai: document.getElementById('pasar-kurs-nilai'),
    kursPerubahan: document.getElementById('pasar-kurs-perubahan'),
    kursCatatan: document.getElementById('pasar-kurs-catatan'),
    formFilter: document.getElementById('form-filter'),
    cari: document.getElementById('cari'),
    tahun: document.getElementById('filter-tahun'),
    urutan: document.getElementById('filter-urutan'),
    infoJumlah: document.getElementById('info-jumlah'),
    daftar: document.getElementById('daftar-pidato'),
    pagination: document.getElementById('pagination'),
    judulSemua: document.getElementById('semua-pidato-title')
};

function bacaStateDariUrl() {
    const parameter = new URLSearchParams(location.search);
    const halaman = Number.parseInt(parameter.get('halaman') ?? '1', 10);
    const putar = Number.parseInt(parameter.get('putar') ?? '', 10);
    const urutan = parameter.get('urutan') ?? 'terbaru';

    return {
        kataKunci: (parameter.get('q') ?? '').slice(0, 100),
        tahun: /^\d{4}$/.test(parameter.get('tahun') ?? '') ? parameter.get('tahun') : '',
        urutan: daftarUrutan.includes(urutan) ? urutan : 'terbaru',
        halaman: Number.isInteger(halaman) && halaman > 0 ? halaman : 1,
        putar: Number.isInteger(putar) && putar > 0 ? putar : null
    };
}

const state = bacaStateDariUrl();
const pidatoDiHalaman = new Map();
let nomorPermintaanDaftar = 0;
let nomorPermintaanPasar = 0;
let pidatoAktif = null;
let riwayatPasar = [];

function simpanStateKeUrl() {
    const parameter = new URLSearchParams();
    if (state.putar) parameter.set('putar', state.putar);
    if (state.kataKunci) parameter.set('q', state.kataKunci);
    if (state.tahun) parameter.set('tahun', state.tahun);
    if (state.urutan !== 'terbaru') parameter.set('urutan', state.urutan);
    if (state.halaman > 1) parameter.set('halaman', state.halaman);

    const query = parameter.toString();
    history.replaceState(null, '', query ? `?${query}` : location.pathname);
}

function isiStatistik(ringkasan) {
    elemen.statTotal.textContent = ringkasan.total_pidato.toLocaleString('id-ID');
    elemen.statSejak.textContent = ringkasan.sejak
        ? `Sejak ${formatBulanTahun.format(new Date(ringkasan.sejak.replace(' ', 'T')))}`
        : 'Belum ada pidato';

    if (ringkasan.rata_rata_ihsg === null) {
        elemen.statRata.textContent = '–';
        elemen.statRataCatatan.textContent = 'Belum ada data hari bursa';
    } else {
        elemen.statRata.textContent = teksPersen(ringkasan.rata_rata_ihsg);
        elemen.statRata.className = `nilai-dampak ${kelasArah(ringkasan.rata_rata_ihsg)}`;
        elemen.statRataCatatan.textContent = `Dari ${ringkasan.jumlah_hari_bursa} pidato pada hari bursa aktif`;
    }

    const terbesar = ringkasan.kenaikan_ihsg_terbesar;
    if (terbesar === null) {
        elemen.statTerbesar.textContent = '–';
        elemen.statTerbesarCatatan.textContent = 'Belum ada data hari bursa';
    } else {
        elemen.statTerbesar.textContent = teksPersen(terbesar.perubahan);
        elemen.statTerbesar.className = `nilai-dampak ${kelasArah(terbesar.perubahan)}`;
        const tanggal = formatTanggalSingkat.format(new Date(terbesar.tayang_pada.replace(' ', 'T')));
        elemen.statTerbesarCatatan.textContent = `${terbesar.judul}, ${tanggal}`;
        elemen.statTerbesarCatatan.title = elemen.statTerbesarCatatan.textContent;
    }

    const pilihanTerpasang = new Set([...elemen.tahun.options].map(opsi => opsi.value));
    ringkasan.daftar_tahun
        .filter(tahun => !pilihanTerpasang.has(String(tahun)))
        .forEach(tahun => elemen.tahun.append(buatElemen('option', { value: tahun, teks: tahun })));
    elemen.tahun.value = state.tahun;
}

function aturStatusPerubahan(elemenTeks, teks, kelas) {
    elemenTeks.textContent = teks;
    elemenTeks.classList.toggle('naik', kelas === 'naik');
    elemenTeks.classList.toggle('turun', kelas === 'turun');
}

function tampilkanStatusGrafik(pesan) {
    riwayatPasar = [];
    elemen.grafik.replaceChildren(buatElemen('p', { kelas: 'chart-status', teks: pesan }));
}

function gambarUlangGrafik() {
    if (riwayatPasar.length > 1 && pidatoAktif) {
        gambarGrafikIHSG(elemen.grafik, riwayatPasar, { tandaTanggal: pidatoAktif.tayang_pada.slice(0, 10) });
    }
}

async function muatDataPasar(pidato) {
    const nomor = ++nomorPermintaanPasar;
    const tanggal = pidato.tayang_pada.slice(0, 10);

    elemen.judulPasar.textContent = `IHSG & Nilai Tukar Rupiah, ${formatTanggal(pidato.tayang_pada)}`;
    elemen.ihsgNilai.textContent = 'Memuat IHSG...';
    aturStatusPerubahan(elemen.ihsgPerubahan, '-', 'netral');
    elemen.kursNilai.textContent = 'Memuat kurs...';
    aturStatusPerubahan(elemen.kursPerubahan, '1 USD = ... IDR', 'netral');
    elemen.kursCatatan.textContent = 'Mengambil kurs referensi...';
    tampilkanStatusGrafik('Memuat grafik IHSG...');

    try {
        const { data } = await panggilApi(`${endpointPasar}?tanggal=${encodeURIComponent(tanggal)}`);
        if (nomor !== nomorPermintaanPasar) return;

        const { ihsg, kurs } = data;
        if (ihsg.status_pasar === 'Buka') {
            elemen.ihsgNilai.textContent = formatIndeks.format(ihsg.tutup);
            aturStatusPerubahan(elemen.ihsgPerubahan, `${teksPersen(ihsg.perubahan_persen)} pada hari pidato`, kelasArah(ihsg.perubahan_persen));
        } else if (ihsg.status_pasar === 'Libur') {
            elemen.ihsgNilai.textContent = 'Pasar Libur';
            aturStatusPerubahan(elemen.ihsgPerubahan, 'Bursa tutup pada hari pidato', 'netral');
        } else {
            elemen.ihsgNilai.textContent = 'Menunggu data';
            aturStatusPerubahan(elemen.ihsgPerubahan, 'Harga penutupan hari itu belum tersedia', 'netral');
        }

        riwayatPasar = ihsg.riwayat;
        if (!gambarGrafikIHSG(elemen.grafik, riwayatPasar, { tandaTanggal: tanggal })) {
            tampilkanStatusGrafik('Riwayat IHSG untuk tanggal ini belum tersedia.');
        }

        if (kurs.nilai === null) {
            elemen.kursNilai.textContent = 'Kurs tidak tersedia';
            aturStatusPerubahan(elemen.kursPerubahan, kurs.pasangan, 'netral');
            elemen.kursCatatan.textContent = 'Kurs acuan untuk tanggal ini belum tersedia.';
            return;
        }

        const nilaiRupiah = formatRupiah.format(kurs.nilai);
        const dampakKurs = teksDampakKurs({ status_pasar: kurs.status_pasar, perubahan: kurs.perubahan_poin });
        elemen.kursNilai.textContent = nilaiRupiah;
        aturStatusPerubahan(elemen.kursPerubahan, `1 USD = ${nilaiRupiah} · ${dampakKurs.teks}`, dampakKurs.kelas);
        elemen.kursCatatan.textContent = `Kurs acuan ${formatTanggal(kurs.tanggal_acuan)}. Sumber: Frankfurter.`;
    } catch (error) {
        if (nomor !== nomorPermintaanPasar) return;
        elemen.ihsgNilai.textContent = 'Data pasar tidak tersedia';
        elemen.kursNilai.textContent = 'Kurs tidak tersedia';
        elemen.kursCatatan.textContent = `Gagal memuat data pasar: ${error.message}`;
        tampilkanStatusGrafik('Grafik IHSG gagal dimuat.');
    }
}

function putarVideo() {
    if (!pidatoAktif) return;

    elemen.video.replaceChildren(buatElemen('iframe', {
        src: `${pidatoAktif.url_video}?autoplay=1`,
        title: pidatoAktif.judul,
        allow: 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
        allowfullscreen: true,
        kelas: 'stream-frame'
    }));
    elemen.video.removeAttribute('role');
    elemen.video.removeAttribute('tabindex');
    elemen.video.removeAttribute('aria-label');
    elemen.tombolPutar.disabled = true;
    elemen.tombolPutar.textContent = 'Sedang Diputar';
}

function tampilkanPidato(pidato, { dariArsip = false, langsungPutar = false } = {}) {
    if (!pidato) {
        elemen.judul.textContent = 'Belum ada pidato di arsip.';
        return;
    }

    pidatoAktif = pidato;
    elemen.labelPemutar.textContent = dariArsip ? 'Diputar dari Arsip' : 'Pidato Unggulan';
    elemen.badge.textContent = dariArsip ? 'ARSIP PIDATO' : 'PIDATO TERBARU';
    elemen.judul.textContent = pidato.judul;
    elemen.sumber.textContent = pidato.sumber;
    elemen.tanggal.textContent = formatTanggal(pidato.tayang_pada);
    elemen.tanggal.dateTime = pidato.tayang_pada.replace(' ', 'T');
    elemen.durasi.textContent = formatDurasi(pidato.durasi_detik) || '-';
    elemen.deskripsi.textContent = pidato.deskripsi ?? '';
    elemen.deskripsi.hidden = !pidato.deskripsi;

    const isiVideo = [buatElemen('span', { kelas: 'stream-overlay', 'aria-hidden': 'true' }, buatElemen('span', { kelas: 'unggulan-play-ikon' }))];
    if (pidato.thumbnail) {
        const gambar = buatElemen('img', { src: pidato.thumbnail, alt: `Cuplikan video: ${pidato.judul}`, kelas: 'stream-preview' });
        gambar.addEventListener('error', () => gambar.remove(), { once: true });
        isiVideo.unshift(gambar);
    }
    elemen.video.replaceChildren(...isiVideo);
    elemen.video.setAttribute('role', 'button');
    elemen.video.setAttribute('tabindex', '0');
    elemen.video.setAttribute('aria-label', `Putar ${pidato.judul}`);

    elemen.tombolPutar.disabled = false;
    elemen.tombolPutar.textContent = 'Putar Pidato';

    muatDataPasar(pidato);
    if (langsungPutar) putarVideo();
}

function putarDariArsip(pidato) {
    state.putar = pidato.id;
    simpanStateKeUrl();
    tampilkanPidato(pidato, { dariArsip: true, langsungPutar: true });
    elemen.pemutar.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function muatPemutarAwal() {
    if (state.putar) {
        try {
            const { data } = await panggilApi(`${endpointPidato}?id=${state.putar}`);
            tampilkanPidato(data, { dariArsip: true, langsungPutar: true });
            return;
        } catch {
            state.putar = null;
            simpanStateKeUrl();
        }
    }
    await muatPidatoTerbaru();
}

async function muatPidatoTerbaru() {
    try {
        const hasil = await panggilApi(`${endpointPidato}?per_halaman=1&urutan=terbaru`);
        tampilkanPidato(hasil.data[0]);
    } catch (error) {
        elemen.judul.textContent = `Gagal memuat pidato: ${error.message}`;
    }
}

async function muatDaftar({ gulir = false } = {}) {
    const nomor = ++nomorPermintaanDaftar;
    const parameter = new URLSearchParams({ halaman: state.halaman, per_halaman: jumlahPerHalaman, urutan: state.urutan });
    if (state.kataKunci) parameter.set('q', state.kataKunci);
    if (state.tahun) parameter.set('tahun', state.tahun);

    simpanStateKeUrl();
    elemen.daftar.setAttribute('aria-busy', 'true');
    elemen.daftar.replaceChildren(...buatKartuMemuat(jumlahPerHalaman));
    if (gulir) elemen.judulSemua.scrollIntoView({ behavior: 'smooth', block: 'start' });

    try {
        const hasil = await panggilApi(`${endpointPidato}?${parameter}`);
        if (nomor !== nomorPermintaanDaftar) return;

        const { total_data: totalData, total_halaman: totalHalaman } = hasil.halaman;
        if (state.halaman > 1 && state.halaman > totalHalaman) {
            state.halaman = Math.max(1, totalHalaman);
            return muatDaftar();
        }

        isiStatistik(hasil.ringkasan);

        const awal = totalData === 0 ? 0 : (state.halaman - 1) * jumlahPerHalaman + 1;
        const akhir = Math.min(state.halaman * jumlahPerHalaman, totalData);
        elemen.infoJumlah.textContent = `Menampilkan ${awal}–${akhir} dari ${totalData} pidato`;

        pidatoDiHalaman.clear();
        hasil.data.forEach(pidato => pidatoDiHalaman.set(String(pidato.id), pidato));

        elemen.daftar.replaceChildren(...(hasil.data.length > 0
            ? hasil.data.map(buatKartuPidato)
            : [buatElemen('p', { kelas: 'arsip-kosong', teks: 'Tidak ada pidato yang cocok dengan pencarian atau filter.' })]));

        tampilkanPagination(elemen.pagination, state.halaman, totalHalaman, halaman => {
            state.halaman = halaman;
            muatDaftar({ gulir: true });
        });
    } catch (error) {
        if (nomor !== nomorPermintaanDaftar) return;
        elemen.infoJumlah.textContent = '';
        elemen.pagination.replaceChildren();
        elemen.daftar.replaceChildren(buatElemen('p', { kelas: 'arsip-kosong', teks: `Gagal memuat arsip: ${error.message}` }));
    } finally {
        if (nomor === nomorPermintaanDaftar) elemen.daftar.removeAttribute('aria-busy');
    }
}

elemen.daftar.addEventListener('click', peristiwa => {
    const tautan = peristiwa.target.closest('a[data-putar]');
    if (!tautan || peristiwa.ctrlKey || peristiwa.metaKey || peristiwa.shiftKey || peristiwa.button !== 0) return;

    const pidato = pidatoDiHalaman.get(tautan.dataset.putar);
    if (!pidato) return;

    peristiwa.preventDefault();
    putarDariArsip(pidato);
});

elemen.tombolPutar.addEventListener('click', putarVideo);
elemen.video.addEventListener('click', () => {
    if (elemen.video.getAttribute('role') === 'button') putarVideo();
});
elemen.video.addEventListener('keydown', peristiwa => {
    if (elemen.video.getAttribute('role') === 'button' && (peristiwa.key === 'Enter' || peristiwa.key === ' ')) {
        peristiwa.preventDefault();
        putarVideo();
    }
});

if ('ResizeObserver' in window) {
    new ResizeObserver(gambarUlangGrafik).observe(elemen.grafik);
}

let jedaPencarian;
elemen.cari.addEventListener('input', () => {
    clearTimeout(jedaPencarian);
    jedaPencarian = setTimeout(() => {
        state.kataKunci = elemen.cari.value.trim();
        state.halaman = 1;
        muatDaftar();
    }, 350);
});

elemen.tahun.addEventListener('change', () => {
    state.tahun = elemen.tahun.value;
    state.halaman = 1;
    muatDaftar();
});

elemen.urutan.addEventListener('change', () => {
    state.urutan = elemen.urutan.value;
    state.halaman = 1;
    muatDaftar();
});

elemen.formFilter.addEventListener('submit', peristiwa => peristiwa.preventDefault());

elemen.cari.value = state.kataKunci;
elemen.urutan.value = state.urutan;

await Promise.all([muatPemutarAwal(), muatDaftar()]);

sinkronOtomatis(async () => {
    await muatDaftar();
    if (!state.putar && elemen.tombolPutar.disabled === false) await muatPidatoTerbaru();
});
