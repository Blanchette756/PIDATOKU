import { formatAngka, filterPidatoByKategori, hitungRataRataIHSG, perbaruiElemenTeks } from './utils.js';

const dataPasar = {
    ihsg: { nilai: 7250.45, perubahan: '+0,8%' },
    usdIdr: { nilai: 15600, perubahan: '+25 poin' }
};

const dataPidato = [
    { id: 1, judul: 'Pidato RUU APBN 2027', tanggal: '2026-08-16', kategori: 'Ekonomi', dampakIHSG: 0.8, statusPasar: 'Buka' },
    { id: 2, judul: 'Pidato Kenegaraan HUT RI', tanggal: '2026-08-17', kategori: 'Geopolitik', dampakIHSG: null, statusPasar: 'Libur' }
];

function inisialisasiAplikasi() {
    try {
        console.log('=== MEMATANGKAN DATA PIDATOKU ===');

        perbaruiElemenTeks('ihsg-value', formatAngka(dataPasar.ihsg.nilai));
        perbaruiElemenTeks('usd-value', `Rp${dataPasar.usdIdr.nilai.toLocaleString('id-ID')}`);

        const pidatoEkonomi = filterPidatoByKategori(dataPidato, 'Ekonomi');
        console.log('Hasil Filter Pidato Ekonomi:', pidatoEkonomi);

        const rataIHSG = hitungRataRataIHSG(dataPidato);
        console.log(`Rata-rata Dampak IHSG: +${rataIHSG}%`);

        filterPidatoByKategori('bukan-array', 'Ekonomi');
    } catch (error) {
        console.warn('Errort terdeteksi:', error.message);
    }
}

document.addEventListener('DOMContentLoaded', inisialisasiAplikasi);