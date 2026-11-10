const svgNS = 'http://www.w3.org/2000/svg';
let nomorGradien = 0;

function buatElemenSvg(nama, atribut = {}) {
    const elemen = document.createElementNS(svgNS, nama);
    Object.entries(atribut).forEach(([kunci, nilai]) => elemen.setAttribute(kunci, String(nilai)));
    return elemen;
}

export function gambarGrafikIHSG(wadah, riwayat, { tandaTanggal = null, labelTanda = 'Hari pidato' } = {}) {
    const lebar = wadah.clientWidth;
    const tinggi = wadah.clientHeight;
    if (riwayat.length < 2 || lebar === 0 || tinggi === 0) return false;

    const tepi = { kiri: 12, kanan: 64, atas: tandaTanggal ? 26 : 16, bawah: 28 };
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
    const idGradien = `ihsg-area-${++nomorGradien}`;

    const svg = buatElemenSvg('svg', { class: 'ihsg-svg', viewBox: `0 0 ${lebar} ${tinggi}`, 'aria-hidden': 'true' });
    const defs = buatElemenSvg('defs');
    const gradien = buatElemenSvg('linearGradient', { id: idGradien, x1: 0, y1: 0, x2: 0, y2: 1 });
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
            fill: `url(#${idGradien})`
        }),
        buatElemenSvg('polyline', { class: 'ihsg-line', points: titik.join(' '), stroke: warna })
    );

    if (tandaTanggal) {
        let indeksTanda = riwayat.findIndex(item => item.tanggal >= tandaTanggal);
        if (indeksTanda === -1) indeksTanda = indeksTerakhir;
        const x = posisiX(indeksTanda);
        const hariBursa = riwayat[indeksTanda].tanggal === tandaTanggal;
        const posisiTeks = indeksTanda < indeksTerakhir * 0.2 ? 'start' : indeksTanda > indeksTerakhir * 0.8 ? 'end' : 'middle';
        const label = buatElemenSvg('text', { class: 'ihsg-tanda-label', x, y: 14, 'text-anchor': posisiTeks });
        label.textContent = labelTanda;

        svg.append(
            buatElemenSvg('line', { class: 'ihsg-tanda', x1: x, y1: tepi.atas - 6, x2: x, y2: dasar }),
            label
        );
        if (hariBursa) {
            svg.append(buatElemenSvg('circle', { class: 'ihsg-tanda-titik', cx: x, cy: posisiY(riwayat[indeksTanda].tutup), r: 5 }));
        }
    } else {
        svg.append(buatElemenSvg('circle', { cx: posisiX(indeksTerakhir), cy: posisiY(daftarNilai[indeksTerakhir]), r: 3.5, fill: warna }));
    }

    wadah.replaceChildren(svg);
    return true;
}
