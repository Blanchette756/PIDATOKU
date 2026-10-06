const DRAFT_KEY = 'pidatoku.draftMasukan';
const FIELD_IDS = ['nama_lengkap', 'email_pengguna', 'kategori_masukan', 'detail_pesan'];

function normalisasiInput(data) {
    return {
        nama: data.nama.trim(),
        email: data.email.trim().toLowerCase(),
        kategori: data.kategori,
        pesan: data.pesan.trim()
    };
}

function validasiFormulir(data) {
    const errors = {};

    if (data.nama.length === 0) {
        errors.nama_lengkap = 'Nama lengkap wajib diisi.';
    } else if (data.nama.length < 3) {
        errors.nama_lengkap = 'Nama minimal 3 karakter.';
    }

    if (data.email.length === 0) {
        errors.email_pengguna = 'Alamat email wajib diisi.';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) {
        errors.email_pengguna = 'Format email tidak valid (contoh: nama@domain.com).';
    }

    if (data.kategori === '') {
        errors.kategori_masukan = 'Pilih salah satu kategori masukan.';
    }

    if (data.pesan.length === 0) {
        errors.detail_pesan = 'Detail pesan wajib diisi.';
    } else if (data.pesan.length < 10) {
        errors.detail_pesan = 'Pesan terlalu singkat, minimal 10 karakter.';
    }

    return errors;
}

function tampilkanError(errors) {
    const ringkasan = document.getElementById('ringkasan-error');
    const daftarError = document.getElementById('daftar-error');

    FIELD_IDS.forEach(id => {
        const input = document.getElementById(id);
        const spanError = document.getElementById(`error-${id}`);

        if (errors[id]) {
            input.setAttribute('aria-invalid', 'true');
            input.classList.remove('field-valid');
            spanError.textContent = errors[id];
        } else {
            input.removeAttribute('aria-invalid');
            input.classList.add('field-valid');
            spanError.textContent = '';
        }
    });

    const daftarKunci = Object.keys(errors);
    if (daftarKunci.length > 0) {
        daftarError.replaceChildren();
        daftarKunci.forEach(id => {
            const li = document.createElement('li');
            const link = document.createElement('a');
            link.href = `#${id}`;
            link.textContent = errors[id];
            link.addEventListener('click', peristiwa => {
                peristiwa.preventDefault();
                document.getElementById(id).focus();
            });
            li.append(link);
            daftarError.append(li);
        });
        ringkasan.hidden = false;
        ringkasan.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        ringkasan.hidden = true;
        daftarError.replaceChildren();
    }
}

function bersihkanFormulir(form) {
    form.reset();
    FIELD_IDS.forEach(id => {
        const input = document.getElementById(id);
        const spanError = document.getElementById(`error-${id}`);
        input.removeAttribute('aria-invalid');
        input.classList.remove('field-valid');
        spanError.textContent = '';
    });
    document.getElementById('ringkasan-error').hidden = true;
    document.getElementById('daftar-error').replaceChildren();
}

function simpanDraft() {
    try {
        const draft = {};
        FIELD_IDS.forEach(id => {
            draft[id] = document.getElementById(id).value;
        });
        localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));

        const indikator = document.getElementById('draft-status');
        indikator.textContent = 'Draft tersimpan';
        indikator.className = 'draft-indicator draft-tersimpan';
    } catch (error) {
        console.warn('Gagal menyimpan draft:', error.message);
    }
}

function muatDraft() {
    try {
        const tersimpan = localStorage.getItem(DRAFT_KEY);
        if (!tersimpan) return;

        const draft = JSON.parse(tersimpan);
        FIELD_IDS.forEach(id => {
            const input = document.getElementById(id);
            if (draft[id]) input.value = draft[id];
        });

        const indikator = document.getElementById('draft-status');
        indikator.textContent = 'Draft dimuat dari penyimpanan lokal';
        indikator.className = 'draft-indicator draft-tersimpan';
    } catch (error) {
        console.warn('Gagal memuat draft:', error.message);
    }
}

function hapusDraft() {
    try {
        localStorage.removeItem(DRAFT_KEY);
        const indikator = document.getElementById('draft-status');
        indikator.textContent = '';
        indikator.className = 'draft-indicator';
    } catch (error) {
        console.warn('Gagal menghapus draft:', error.message);
    }
}

export function inisialisasiFormValidasi() {
    const form = document.getElementById('form-masukan');
    if (!form) return;

    muatDraft();

    FIELD_IDS.forEach(id => {
        const input = document.getElementById(id);
        input.addEventListener('input', simpanDraft);
    });

    form.addEventListener('submit', peristiwa => {
        peristiwa.preventDefault();

        const mentah = {
            nama: document.getElementById('nama_lengkap').value,
            email: document.getElementById('email_pengguna').value,
            kategori: document.getElementById('kategori_masukan').value,
            pesan: document.getElementById('detail_pesan').value
        };

        const bersih = normalisasiInput(mentah);
        const errors = validasiFormulir(bersih);
        tampilkanError(errors);

        const daftarKunci = Object.keys(errors);
        if (daftarKunci.length > 0) {
            document.getElementById(daftarKunci[0]).focus();
            return;
        }

        hapusDraft();
        bersihkanFormulir(form);

        console.log('Data terkirim:', bersih);
        alert('Masukan berhasil dikirim. Terima kasih!');
    });
}
