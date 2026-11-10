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
    return { teks: teksPersen(nilai), kelas: kelasArah(nilai) };
}

export function teksPersen(nilai) {
    return `${formatPersen.format(nilai).replace('-', '−')}%`;
}

export function kelasArah(nilai) {
    return nilai > 0 ? 'naik' : nilai < 0 ? 'turun' : 'netral';
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

function ikonGambar() {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    svg.classList.add('thumb-ikon');
    svg.innerHTML = '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>';
    return svg;
}

export function buatThumbnail(pidato, { besar = false } = {}) {
    const durasi = formatDurasi(pidato.durasi_detik);
    const wadah = buatElemen('figure', { kelas: `thumb${besar ? ' thumb-besar' : ''}` }, [
        buatElemen('span', { kelas: 'thumb-placeholder' }, [
            ikonGambar(),
            buatElemen('span', { kelas: 'thumb-label', teks: 'Thumbnail Pidato · 16:9' })
        ]),
        durasi ? buatElemen('span', { kelas: 'thumb-durasi', teks: durasi, 'aria-label': `Durasi ${durasi}` }) : null
    ]);

    if (pidato.thumbnail) {
        const gambar = buatElemen('img', {
            src: pidato.thumbnail,
            alt: `Cuplikan video: ${pidato.judul}`,
            loading: besar ? 'eager' : 'lazy',
            decoding: 'async'
        });
        gambar.addEventListener('error', () => gambar.remove(), { once: true });
        wadah.prepend(gambar);
    }

    return wadah;
}

export function buatMetaPidato(pidato) {
    return buatElemen('header', { kelas: 'kartu-meta' }, [
        pidato.kategori ? buatElemen('span', { kelas: 'speech-badge', teks: pidato.kategori }) : null,
        buatElemen('time', {
            kelas: 'kartu-tanggal',
            datetime: pidato.tayang_pada.replace(' ', 'T'),
            teks: formatTanggal(pidato.tayang_pada)
        })
    ]);
}

function barisDampak(label, hasil) {
    return buatElemen('p', { kelas: 'kartu-dampak-baris' }, [
        buatElemen('span', { kelas: 'kartu-dampak-label', teks: label }),
        buatElemen('span', { kelas: `nilai-dampak ${hasil.kelas}`, teks: hasil.teks })
    ]);
}

export function tautanPutar(pidato) {
    return `arsip.html?putar=${encodeURIComponent(pidato.id)}`;
}

export function buatKartuPidato(pidato) {
    const tautan = tautanPutar(pidato);
    const thumbnail = buatThumbnail(pidato);
    thumbnail.append(buatElemen('span', { kelas: 'thumb-putar', 'aria-hidden': 'true' }));

    return buatElemen('article', { kelas: 'kartu-pidato' }, [
        buatElemen('a', { href: tautan, kelas: 'kartu-thumb-tautan', tabindex: '-1', 'aria-hidden': 'true', 'data-putar': pidato.id },
            thumbnail),
        buatElemen('section', { kelas: 'kartu-isi' }, [
            buatMetaPidato(pidato),
            buatElemen('h3', { kelas: 'kartu-judul' },
                buatElemen('a', { href: tautan, 'data-putar': pidato.id, teks: pidato.judul, title: `Putar: ${pidato.judul}` })),
            buatElemen('footer', { kelas: 'kartu-dampak' }, [
                barisDampak('IHSG', teksDampakIhsg(pidato.dampak_pasar.ihsg)),
                barisDampak('Kurs IDR', teksDampakKurs(pidato.dampak_pasar.kurs))
            ])
        ])
    ]);
}

export function buatKartuMemuat(jumlah) {
    return Array.from({ length: jumlah }, () => buatElemen('article', { kelas: 'kartu-pidato kartu-memuat', 'aria-hidden': 'true' }, [
        buatElemen('figure', { kelas: 'thumb' }),
        buatElemen('section', { kelas: 'kartu-isi' }, [
            buatElemen('span', { kelas: 'garis-memuat pendek' }),
            buatElemen('span', { kelas: 'garis-memuat' }),
            buatElemen('span', { kelas: 'garis-memuat' })
        ])
    ]));
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
