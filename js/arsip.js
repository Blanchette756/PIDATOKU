export const endpointPidato = './api/pidato.php';
export const endpointSinkron = './api/sinkron.php';

const formatTanggalPanjang = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
const formatTanggalPendek = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
const formatPersen = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 2, signDisplay: 'exceptZero' });
const formatPoin = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });

export function tanggalDariMysql(nilai) {
    const [tanggal, jam = '00:00:00'] = String(nilai).split(' ');
    return new Date(`${tanggal}T${jam}`);
}

export function formatTanggal(nilai, pendek = false) {
    const tanggal = tanggalDariMysql(nilai);
    return (pendek ? formatTanggalPendek : formatTanggalPanjang).format(tanggal);
}

export function formatDurasi(detik) {
    if (!Number.isFinite(detik) || detik <= 0) return '';
    const jam = Math.floor(detik / 3600);
    const menit = Math.floor((detik % 3600) / 60);
    const sisa = String(detik % 60).padStart(2, '0');
    return jam > 0 ? `${jam}:${String(menit).padStart(2, '0')}:${sisa}` : `${menit}:${sisa}`;
}

export function teksDampakIhsg(dampak) {
    if (!dampak || dampak.status_pasar === 'Menunggu') return { teks: 'Menunggu data', kelas: 'netral' };
    if (dampak.status_pasar === 'Libur') return { teks: 'Pasar Libur', kelas: 'netral' };

    const nilai = Number(dampak.perubahan);
    return {
        teks: `${formatPersen.format(nilai)}%`,
        kelas: nilai > 0 ? 'naik' : nilai < 0 ? 'turun' : 'netral'
    };
}

export function teksDampakKurs(dampak) {
    if (!dampak || dampak.status_pasar === 'Menunggu') return { teks: 'Menunggu data', kelas: 'netral' };
    if (dampak.status_pasar === 'Libur') return { teks: 'Pasar Libur', kelas: 'netral' };

    const nilai = Number(dampak.perubahan);
    if (nilai === 0) return { teks: 'Stabil', kelas: 'netral' };
    return {
        teks: `${nilai > 0 ? 'Menguat' : 'Melemah'} ${formatPoin.format(Math.abs(nilai))} poin`,
        kelas: nilai > 0 ? 'naik' : 'turun'
    };
}

export function buatElemen(nama, atribut = {}, isi = []) {
    const elemen = document.createElement(nama);
    for (const [kunci, nilai] of Object.entries(atribut)) {
        if (nilai === null || nilai === undefined || nilai === false) continue;
        if (kunci === 'teks') elemen.textContent = nilai;
        else if (kunci === 'kelas') elemen.className = nilai;
        else elemen.setAttribute(kunci, nilai === true ? '' : nilai);
    }
    elemen.append(...[].concat(isi).filter(Boolean));
    return elemen;
}

function daftarNomorHalaman(sekarang, total) {
    const nomor = new Set([1, total, sekarang - 1, sekarang, sekarang + 1]);
    const urut = [...nomor].filter(n => n >= 1 && n <= total).sort((a, b) => a - b);
    const hasil = [];

    urut.forEach((n, i) => {
        if (i > 0 && n - urut[i - 1] > 1) hasil.push(null);
        hasil.push(n);
    });
    return hasil;
}

export function tampilkanPagination(wadah, sekarang, total, saatPilih) {
    wadah.replaceChildren();
    if (total <= 1) return;

    const tombol = (teks, halaman, atribut = {}) => {
        const elemen = buatElemen('button', { type: 'button', kelas: 'page-btn', teks, ...atribut });
        elemen.addEventListener('click', () => saatPilih(halaman));
        return elemen;
    };

    wadah.append(tombol('‹ Sebelumnya', sekarang - 1, { disabled: sekarang === 1, 'aria-label': 'Halaman sebelumnya' }));

    for (const nomor of daftarNomorHalaman(sekarang, total)) {
        if (nomor === null) {
            wadah.append(buatElemen('span', { kelas: 'page-gap', teks: '…', 'aria-hidden': 'true' }));
            continue;
        }
        wadah.append(tombol(String(nomor), nomor, {
            'aria-label': `Halaman ${nomor}`,
            'aria-current': nomor === sekarang ? 'page' : null
        }));
    }

    wadah.append(tombol('Berikutnya ›', sekarang + 1, { disabled: sekarang === total, 'aria-label': 'Halaman berikutnya' }));
}

export class GalatApi extends Error {
    constructor(status, isi) {
        super(isi?.pesan ?? `HTTP ${status}`);
        this.status = status;
        this.aturan = isi?.aturan ?? {};
    }
}

export async function panggilApi(url, opsi = {}) {
    const respons = await fetch(url, {
        ...opsi,
        headers: { Accept: 'application/json', ...(opsi.body ? { 'Content-Type': 'application/json' } : {}) }
    });

    if (respons.status === 204) return null;

    const isi = await respons.json().catch(() => null);
    if (!respons.ok) throw new GalatApi(respons.status, isi);
    return isi;
}

export async function sinkronOtomatis(saatBerubah, saatStatus) {
    const batasPutaran = 15;

    for (let putaran = 0; putaran < batasPutaran; putaran++) {
        let hasil;
        try {
            hasil = (await panggilApi(endpointSinkron, { method: 'POST' })).data;
        } catch (error) {
            saatStatus?.({ gagal: true, pesan: error.message });
            return;
        }

        if (hasil.pidato_ditambahkan > 0 || hasil.dampak_pasar_diperbarui > 0) {
            await saatBerubah?.(hasil);
        }

        saatStatus?.(hasil);
        if (hasil.arsip_lengkap || hasil.keadaan !== 'selesai') return;
    }
}
