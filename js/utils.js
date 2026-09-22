export function formatAngka(angka) {
    if (typeof angka !== 'number') throw new TypeError('Input harus berupa angka.');
    return angka.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export function filterPidatoByKategori(data, kategori) {
    if (!Array.isArray(data)) throw new TypeError('Data pidato harus berbentuk array.');
    return data.filter(item => item.kategori === kategori);
}

export const hitungRataRataIHSG = (data) => {
    try {
        if (!Array.isArray(data) || data.length === 0) return 0;
        const dataAktif = data.filter(item => typeof item.dampakIHSG === 'number');
        if (dataAktif.length === 0) return 0;

        const total = dataAktif.reduce((acc, curr) => acc + curr.dampakIHSG, 0);
        return (total / dataAktif.length).toFixed(2);
    } catch (error) {
        console.error('Terjadi kesalahan perhitungan IHSG:', error.message);
        return 0;
    }
};

export function perbaruiElemenTeks(idElemen, nilai) {
    try {
        const elemen = document.getElementById(idElemen);
        if (!elemen) throw new Error(`Elemen dengan ID '${idElemen}' tidak ditemukan.`);
        elemen.textContent = nilai;
    } catch (error) {
        console.warn('Gagal memperbarui DOM:', error.message);
    }
}