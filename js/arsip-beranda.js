import {
    endpointPidato,
    buatElemen,
    buatKartuPidato,
    buatKartuMemuat,
    panggilApi,
    sinkronOtomatis
} from './arsip.js';

const jumlahKartu = 4;
const wadah = document.getElementById('arsip-terbaru');

async function muatArsipTerbaru() {
    try {
        const hasil = await panggilApi(`${endpointPidato}?per_halaman=${jumlahKartu}&urutan=terbaru`);
        wadah.replaceChildren(...(hasil.data.length > 0
            ? hasil.data.map(buatKartuPidato)
            : [buatElemen('p', { kelas: 'arsip-kosong', teks: 'Belum ada pidato di arsip.' })]));
    } catch (error) {
        wadah.replaceChildren(buatElemen('p', { kelas: 'arsip-kosong', teks: `Gagal memuat arsip pidato: ${error.message}` }));
    } finally {
        wadah.removeAttribute('aria-busy');
    }
}

if (wadah) {
    wadah.setAttribute('aria-busy', 'true');
    wadah.replaceChildren(...buatKartuMemuat(jumlahKartu));
    await muatArsipTerbaru();
    sinkronOtomatis(muatArsipTerbaru);
}
