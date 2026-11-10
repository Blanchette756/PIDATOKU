import {
    endpointPidato,
    formatTanggal,
    teksDampakIhsg,
    teksDampakKurs,
    buatElemen,
    tampilkanPagination,
    panggilApi,
    sinkronOtomatis
} from './arsip.js';

const jumlahPerHalaman = 10;
const polaTautanYoutube = /^(?:[A-Za-z0-9_-]{11}|(?:https?:\/\/)?(?:www\.|m\.)?(?:youtube\.com\/(?:watch\?(?:.*&)?v=|live\/|embed\/|shorts\/)|youtu\.be\/)[A-Za-z0-9_-]{11}(?:[?&#/].*)?)$/;

const elemen = {
    statusSinkron: document.getElementById('status-sinkron'),
    notifikasi: document.getElementById('notifikasi'),
    form: document.getElementById('form-pidato'),
    judulForm: document.getElementById('judul-form'),
    tombolSimpan: document.getElementById('tombol-simpan'),
    tombolBatal: document.getElementById('tombol-batal'),
    grupTautan: document.getElementById('grup-tautan'),
    grupJudul: document.getElementById('grup-judul'),
    grupTayang: document.getElementById('grup-tayang'),
    grupDeskripsi: document.getElementById('grup-deskripsi'),
    tautan: document.getElementById('tautan_video'),
    judul: document.getElementById('judul'),
    kategori: document.getElementById('kategori'),
    tayang: document.getElementById('tayang_pada'),
    deskripsi: document.getElementById('deskripsi'),
    formFilter: document.getElementById('form-filter'),
    cari: document.getElementById('cari'),
    tahun: document.getElementById('filter-tahun'),
    isiTabel: document.getElementById('isi-tabel'),
    infoJumlah: document.getElementById('info-jumlah'),
    pagination: document.getElementById('pagination'),
    dialogHapus: document.getElementById('dialog-hapus'),
    teksDialogHapus: document.getElementById('teks-dialog-hapus')
};

const state = {
    halaman: 1,
    totalHalaman: 1,
    kataKunci: '',
    tahun: '',
    pidatoDiubah: null,
    pidatoDihapus: null,
    sedangMenyimpan: false,
    tahunTerisi: false
};

function tampilkanNotifikasi(pesan, jenis = 'sukses') {
    elemen.notifikasi.textContent = pesan;
    elemen.notifikasi.className = `notice notice-${jenis}`;
    elemen.notifikasi.hidden = false;
}

function tandaiError(namaField, pesan) {
    const input = document.getElementById(namaField);
    const wadahPesan = document.getElementById(`error-${namaField}`);
    if (!input || !wadahPesan) return false;

    input.setAttribute('aria-invalid', 'true');
    wadahPesan.textContent = pesan;
    return true;
}

function bersihkanError() {
    elemen.form.querySelectorAll('[aria-invalid="true"]').forEach(input => input.removeAttribute('aria-invalid'));
    elemen.form.querySelectorAll('.field-error').forEach(wadah => { wadah.textContent = ''; });
}

function tampilkanErrorServer(error) {
    const sisa = Object.entries(error.aturan).filter(([nama, pesan]) => !tandaiError(nama, pesan));
    const tambahan = sisa.map(([, pesan]) => pesan).join(' ');
    tampilkanNotifikasi(`${error.message}${tambahan ? `. ${tambahan}` : ''}`, 'error');
}

function keNilaiDatetimeLocal(nilaiMysql) {
    return String(nilaiMysql).slice(0, 16).replace(' ', 'T');
}

function sekarangDatetimeLocal() {
    const sekarang = new Date();
    sekarang.setMinutes(sekarang.getMinutes() - sekarang.getTimezoneOffset());
    return sekarang.toISOString().slice(0, 16);
}

function selDampak(dampak, pembuatTeks, label) {
    const { teks, kelas } = pembuatTeks(dampak);
    return buatElemen('td', { 'data-label': label }, buatElemen('span', { kelas: `nilai-dampak ${kelas}`, teks }));
}

function barisPidato(pidato) {
    const tautanVideo = `https://www.youtube.com/watch?v=${encodeURIComponent(pidato.video_id)}`;

    const tombolUbah = buatElemen('button', { type: 'button', kelas: 'btn-aksi', teks: 'Ubah', 'aria-label': `Ubah ${pidato.judul}` });
    tombolUbah.addEventListener('click', () => masukModeUbah(pidato));

    const tombolHapus = buatElemen('button', { type: 'button', kelas: 'btn-aksi btn-aksi-hapus', teks: 'Hapus', 'aria-label': `Hapus ${pidato.judul}` });
    tombolHapus.addEventListener('click', () => konfirmasiHapus(pidato));

    return buatElemen('tr', { 'data-id': pidato.id }, [
        buatElemen('td', { 'data-label': 'Tanggal' },
            buatElemen('time', { datetime: pidato.tayang_pada.replace(' ', 'T'), teks: formatTanggal(pidato.tayang_pada, true) })),
        buatElemen('td', { 'data-label': 'Judul', kelas: 'kolom-judul' },
            buatElemen('a', { href: tautanVideo, target: '_blank', rel: 'noopener noreferrer', teks: pidato.judul })),
        buatElemen('td', { 'data-label': 'Kategori' },
            pidato.kategori
                ? buatElemen('span', { kelas: 'speech-badge', teks: pidato.kategori })
                : buatElemen('span', { kelas: 'teks-redup', teks: 'Belum ada' })),
        selDampak(pidato.dampak_pasar.ihsg, teksDampakIhsg, 'IHSG'),
        selDampak(pidato.dampak_pasar.kurs, teksDampakKurs, 'Kurs IDR'),
        buatElemen('td', { kelas: 'kolom-aksi' }, [tombolUbah, tombolHapus])
    ]);
}

function isiPilihanTahun(daftarTahun) {
    if (state.tahunTerisi) return;
    daftarTahun.forEach(tahun => elemen.tahun.append(buatElemen('option', { value: tahun, teks: tahun })));
    state.tahunTerisi = daftarTahun.length > 0;
}

async function muatDaftar() {
    const parameter = new URLSearchParams({ halaman: state.halaman, per_halaman: jumlahPerHalaman });
    if (state.kataKunci) parameter.set('q', state.kataKunci);
    if (state.tahun) parameter.set('tahun', state.tahun);

    elemen.isiTabel.setAttribute('aria-busy', 'true');

    try {
        const hasil = await panggilApi(`${endpointPidato}?${parameter}`);
        const { total_data: totalData, total_halaman: totalHalaman } = hasil.halaman;

        if (state.halaman > 1 && state.halaman > totalHalaman) {
            state.halaman = Math.max(1, totalHalaman);
            return muatDaftar();
        }

        state.totalHalaman = totalHalaman;
        isiPilihanTahun(hasil.ringkasan.daftar_tahun);

        const awal = totalData === 0 ? 0 : (state.halaman - 1) * jumlahPerHalaman + 1;
        const akhir = Math.min(state.halaman * jumlahPerHalaman, totalData);
        elemen.infoJumlah.textContent = `Menampilkan ${awal}–${akhir} dari ${totalData} pidato`;

        elemen.isiTabel.replaceChildren(...(hasil.data.length > 0
            ? hasil.data.map(barisPidato)
            : [buatElemen('tr', {}, buatElemen('td', { colspan: 6, kelas: 'tabel-kosong', teks: 'Tidak ada pidato yang cocok dengan pencarian.' }))]));

        tampilkanPagination(elemen.pagination, state.halaman, totalHalaman, halaman => {
            state.halaman = halaman;
            muatDaftar();
        });
    } catch (error) {
        elemen.isiTabel.replaceChildren(buatElemen('tr', {},
            buatElemen('td', { colspan: 6, kelas: 'tabel-kosong', teks: `Gagal memuat data: ${error.message}` })));
        elemen.infoJumlah.textContent = '';
    } finally {
        elemen.isiTabel.removeAttribute('aria-busy');
    }
}

function aturMode(pidato) {
    const modeUbah = pidato !== null;
    state.pidatoDiubah = pidato;

    elemen.judulForm.textContent = modeUbah ? 'Ubah Pidato' : 'Tambah Pidato';
    elemen.tombolSimpan.textContent = modeUbah ? 'Simpan Perubahan' : 'Tambah ke Arsip';
    elemen.tombolBatal.hidden = !modeUbah;
    elemen.grupTautan.hidden = modeUbah;
    elemen.grupJudul.hidden = !modeUbah;
    elemen.grupTayang.hidden = !modeUbah;
    elemen.grupDeskripsi.hidden = !modeUbah;

    bersihkanError();
    elemen.form.reset();

    if (modeUbah) {
        elemen.judul.value = pidato.judul;
        elemen.kategori.value = pidato.kategori ?? '';
        elemen.tayang.value = keNilaiDatetimeLocal(pidato.tayang_pada);
        elemen.tayang.max = sekarangDatetimeLocal();
        elemen.deskripsi.value = pidato.deskripsi ?? '';
    }
}

function masukModeUbah(pidato) {
    aturMode(pidato);
    elemen.form.closest('section').scrollIntoView({ behavior: 'smooth', block: 'start' });
    elemen.judul.focus({ preventScroll: true });
}

function validasiTambah() {
    const tautan = elemen.tautan.value.trim();
    if (!tautan) return { tautan_video: 'Link YouTube wajib diisi' };
    if (!polaTautanYoutube.test(tautan)) return { tautan_video: 'Format link YouTube tidak dikenali' };
    return {};
}

function susunPerubahan() {
    const lama = state.pidatoDiubah;
    const errors = {};
    const perubahan = {};

    const judul = elemen.judul.value.trim();
    if (judul.length < 5 || judul.length > 255) errors.judul = 'Judul wajib diisi, 5 sampai 255 karakter';
    else if (judul !== lama.judul) perubahan.judul = judul;

    const kategori = elemen.kategori.value || null;
    if (kategori !== lama.kategori) perubahan.kategori = kategori;

    const tayang = elemen.tayang.value;
    if (!tayang) errors.tayang_pada = 'Tanggal tayang wajib diisi';
    else if (tayang !== keNilaiDatetimeLocal(lama.tayang_pada)) perubahan.tayang_pada = tayang;

    const deskripsi = elemen.deskripsi.value.trim() || null;
    if (deskripsi !== (lama.deskripsi ?? null)) perubahan.deskripsi = deskripsi;

    return { errors, perubahan };
}

async function simpanForm(peristiwa) {
    peristiwa.preventDefault();
    if (state.sedangMenyimpan) return;

    bersihkanError();
    elemen.notifikasi.hidden = true;

    const modeUbah = state.pidatoDiubah !== null;
    let errors;
    let body;

    if (modeUbah) {
        const hasil = susunPerubahan();
        errors = hasil.errors;
        body = hasil.perubahan;

        if (Object.keys(errors).length === 0 && Object.keys(body).length === 0) {
            tampilkanNotifikasi('Tidak ada perubahan yang perlu disimpan.', 'info');
            return;
        }
    } else {
        errors = validasiTambah();
        body = { tautan_video: elemen.tautan.value.trim(), kategori: elemen.kategori.value || null };
    }

    if (Object.keys(errors).length > 0) {
        Object.entries(errors).forEach(([nama, pesan]) => tandaiError(nama, pesan));
        elemen.form.querySelector('[aria-invalid="true"]')?.focus();
        return;
    }

    state.sedangMenyimpan = true;
    elemen.tombolSimpan.disabled = true;
    elemen.tombolSimpan.textContent = modeUbah ? 'Menyimpan...' : 'Mengambil data YouTube...';

    try {
        const url = modeUbah ? `${endpointPidato}?id=${state.pidatoDiubah.id}` : endpointPidato;
        const hasil = await panggilApi(url, { method: modeUbah ? 'PATCH' : 'POST', body: JSON.stringify(body) });

        aturMode(null);
        if (!modeUbah) state.halaman = 1;
        await muatDaftar();
        tampilkanNotifikasi(`${hasil.pesan}: "${hasil.data.judul}"`);
    } catch (error) {
        if (error.status === 404) {
            aturMode(null);
            await muatDaftar();
        }
        tampilkanErrorServer(error);
        elemen.form.querySelector('[aria-invalid="true"]')?.focus();
    } finally {
        state.sedangMenyimpan = false;
        elemen.tombolSimpan.disabled = false;
        if (state.pidatoDiubah === null) elemen.tombolSimpan.textContent = 'Tambah ke Arsip';
        else elemen.tombolSimpan.textContent = 'Simpan Perubahan';
    }
}

function konfirmasiHapus(pidato) {
    state.pidatoDihapus = pidato;
    elemen.teksDialogHapus.textContent = `"${pidato.judul}" (${formatTanggal(pidato.tayang_pada)})`;
    elemen.dialogHapus.showModal();
}

async function hapusPidato() {
    const pidato = state.pidatoDihapus;
    state.pidatoDihapus = null;
    if (elemen.dialogHapus.returnValue !== 'hapus' || !pidato) return;

    try {
        await panggilApi(`${endpointPidato}?id=${pidato.id}`, { method: 'DELETE' });
        if (state.pidatoDiubah?.id === pidato.id) aturMode(null);
        await muatDaftar();
        tampilkanNotifikasi(`Pidato "${pidato.judul}" berhasil dihapus dari arsip`);
    } catch (error) {
        if (error.status === 404) await muatDaftar();
        tampilkanErrorServer(error);
    }
}

function tampilkanStatusSinkron(hasil) {
    if (hasil.gagal) {
        elemen.statusSinkron.textContent = `Sinkron otomatis gagal (${hasil.pesan}). Data yang tampil adalah arsip terakhir.`;
        elemen.statusSinkron.dataset.keadaan = 'gagal';
        return;
    }

    if (!hasil.arsip_lengkap && hasil.keadaan === 'selesai') {
        elemen.statusSinkron.textContent = 'Mengisi arsip dari YouTube, mohon tunggu...';
        elemen.statusSinkron.dataset.keadaan = 'berjalan';
        return;
    }

    const waktu = hasil.sinkron_terakhir
        ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(hasil.sinkron_terakhir))
        : '-';
    elemen.statusSinkron.textContent = `Arsip tersinkron otomatis dengan YouTube. Pemeriksaan terakhir: ${waktu}.`;
    elemen.statusSinkron.dataset.keadaan = 'selesai';
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

elemen.formFilter.addEventListener('submit', peristiwa => peristiwa.preventDefault());
elemen.form.addEventListener('submit', simpanForm);
elemen.tombolBatal.addEventListener('click', () => aturMode(null));
elemen.dialogHapus.addEventListener('close', hapusPidato);
elemen.form.addEventListener('input', peristiwa => {
    if (peristiwa.target.getAttribute('aria-invalid') === 'true') {
        peristiwa.target.removeAttribute('aria-invalid');
        const wadahPesan = document.getElementById(`error-${peristiwa.target.id}`);
        if (wadahPesan) wadahPesan.textContent = '';
    }
});

aturMode(null);
await muatDaftar();
sinkronOtomatis(muatDaftar, tampilkanStatusSinkron);
