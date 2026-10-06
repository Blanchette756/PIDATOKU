import { formatAngka, filterPidatoByKategori, hitungRataRataIHSG } from './utils.js';
import { inisialisasiFormValidasi } from './form-validasi.js';

const endpointFrankfurter = 'https://api.frankfurter.dev/v2';
const mataUangUtama = ['USD', 'EUR', 'JPY', 'GBP', 'SGD', 'AUD', 'CNY'];
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

function muatGrafikIHSG() {
    const wadahGrafik = document.getElementById('ihsg-chart');
    if (!wadahGrafik) return;

    const skripWidget = document.createElement('script');
    skripWidget.src = 'https://s3.tradingview.com/external-embedding/embed-widget-advanced-chart.js';
    skripWidget.async = true;
    skripWidget.textContent = JSON.stringify({
        autosize: true,
        allow_symbol_change: false,
        calendar: false,
        details: false,
        hide_side_toolbar: true,
        hide_top_toolbar: false,
        hide_legend: true,
        hide_volume: true,
        interval: 'D',
        locale: 'id',
        save_image: false,
        style: '1',
        symbol: 'IDX:COMPOSITE',
        theme: 'dark',
        timezone: 'Asia/Jakarta',
        backgroundColor: '#191b1e',
        gridColor: 'rgba(255, 255, 255, 0.06)',
        withdateranges: false,
        support_host: 'https://www.tradingview.com'
    });
    wadahGrafik.append(skripWidget);
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

        try {
            const respons = await fetch(`${endpointFrankfurter}/rate/${kodeMataUang}/IDR`);
            if (!respons.ok) throw new Error('Kurs tidak tersedia untuk pasangan ini.');

            const data = await respons.json();
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
            elemenMataUang.status.textContent = `Kurs acuan harian, ${tanggal}. Sumber: Frankfurter.`;
        } catch (error) {
            elemenMataUang.nilai.textContent = 'Kurs tidak tersedia';
            elemenMataUang.pasangan.textContent = `${kodeMataUang}/IDR`;
            elemenMataUang.status.textContent = 'Data kurs gagal dimuat. Periksa koneksi lalu coba lagi.';
        }
    }

    async function muatDaftarMataUang() {
        elemenMataUang.pilihan.textContent = 'Memuat daftar mata uang...';

        try {
            const respons = await fetch(`${endpointFrankfurter}/currencies`);
            if (!respons.ok) throw new Error('Daftar mata uang gagal dimuat.');

            state.daftarMataUang = await respons.json();
            tampilkanPilihan(elemenMataUang.pencarian.value);
        } catch (error) {
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
        status: document.getElementById('fx-rate-meta')
    };

    muatGrafikIHSG();
    inisialisasiPemilihMataUang(elemenMataUang);
}

document.addEventListener('DOMContentLoaded', () => {
    inisialisasiAplikasi();
    inisialisasiPasar();
    inisialisasiFormValidasi();
});