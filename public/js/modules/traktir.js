/**
 * ====================================================================================
 * MODULE: Oiia Cat Easter Egg & Chaos FX Controller
 * FILE LOCATION: public/js/modules/traktir.js
 * ====================================================================================
 * 
 * Fitur Utama:
 * 1. PENGATURAN TEMPO (BPM):
 *    - Nilai BPM disesuaikan langsung di OIIA_CONFIG (misal: 147 BPM).
 *    - Menghitung ritme ketukan jedag-jedug, goncangan layar, dan kekacauan elemen.
 *    - Kucing ikut jedag-jedug scale membal membesar mengikuti ketukan BPM.
 * 2. KECEPATAN MANTUL DVD BERDASARKAN TIPE:
 *    - State 'medium': kecepatan santai/sedang (dvdSpeedMedium).
 *    - State 'fast': kecepatan ngebut/turbo (dvdSpeedFast) dengan hentakan pantul agresif.
 * 3. EFEK DITERAPKAN DARI BEGIN PEAK HINGGA ENDING (Kecuali IDLE):
 *    - Intro (00:00:100 - 00:06:000): Kucing di pojok kanan bawah, tanpa efek latar.
 *    - Begin Peak s/d End Peak (00:06:000 - 00:42:900): Mantul DVD + jedag-jedug heboh.
 *    - Finishing (00:44:500 - 00:56:900): Kucing di tengah membesar warna-warni + efek latar berlanjut.
 *    - Ending (00:57:300 - 01:00:500): Putaran cepat di tengah + klimaks jedag-jedug, meledak di 00:59:500.
 * 4. SAAT STATE IDLE:
 *    - Seluruh efek, goncangan, warna-warni, dan distorsi elemen langsung hilang & kembali rapi.
 *    - Posisi kucing yang sedang bergerak mantul DVD membeku (diam di tempat), lalu lanjut lagi saat durasi aktif.
 * 5. EFEK SUPER HEBOH BERGANTIAN & GABUNGAN:
 *    - Mode Slam & Squash (Hentakan bass vertikal).
 *    - Mode Violent Twist (Puntiran kartu dan rotasi silang QRIS).
 *    - Mode Disco Strobe (Warna-warni neon & kilatan lampu latar).
 *    - Mode Earthquake Scatter (Div-div judul, icon, QRIS, tombol berhamburan acak).
 *    - Mode Super Overdrive Combo (Gabungan semua efek saat tempo cepat / drop).
 */

document.addEventListener('DOMContentLoaded', () => {
    // --------------------------------------------------------------------------------
    // 1. PENGATURAN TEMPO & KONFIGURASI EFEK (Dapat disesuaikan secara mandiri)
    // --------------------------------------------------------------------------------
    const OIIA_CONFIG = {
        // TEMPO & RITME KETUKAN (BPM - Beats Per Minute):
        // Sesuaikan angka ini dengan tempo lagu cat-song.mp3 (misal: 133, 147, 150).
        // Interval beat dihitung otomatis: (60 / bpm) detik.
        bpm: 147,

        // KECEPATAN MANTUL DVD (Pixel per frame):
        dvdSpeedMedium: 20.0,  // Kecepatan saat varian kucing 'medium'
        dvdSpeedFast:   30.0, // Kecepatan cepat/turbo saat varian kucing 'fast'

        // PENGATUR JEDAG-JEDUG SCALE PADA KUCING:
        catScaleBump: 0.26,   // Hentakan membal ukuran kucing saat beat (0.26 = +26% perbesaran)

        // PENGATUR INTENSITAS JEDAG-JEDUG ELEMEN LATAR (CHAOS):
        // 1.0 = standar heboh, 1.5+ = super brutal
        intensityMultiplier: 1.0,

        // Derajat kemiringan maksimal kartu donasi saat beat (deg)
        tiltAngleMax: 14,

        // Derajat kemiringan maksimal gambar QRIS saat beat (deg)
        qrisTiltMax: 22,

        // Hentakan membal perbesaran ukuran elemen saat beat (0.13 = +13%)
        scaleBumpMax: 0.13,

        // Jarak hamburan elemen acak saat berantakan (pixel)
        scatterDistanceMax: 18,

        // Aktifkan efek kilatan warna strobo latar belakang
        enableStrobeFlash: true,

        // PENGATURAN FINISHING (Kucing di Tengah Layar & Membesar):
        finishScaleStart: 1.2, // Ukuran awal saat mulai tahap finishing
        finishScaleEnd:   3.2, // Ukuran puncak sebelum meledak

        // WAKTU LEDAKAN AKHIR (MM:SS:mmm):
        explosionTime: '00:59:300',
        particleCount: 150     // Jumlah partikel pecahan ledakan
    };

    // Ekspos ke window agar dapat dimonitor atau disesuaikan lewat console
    window.OIIA_CONFIG = OIIA_CONFIG;

    // --------------------------------------------------------------------------------
    // 2. TIMELINE DURASI RESMI DARI USER
    // --------------------------------------------------------------------------------
    const OIIA_TIMELINE = [
        { start: '00:00:100', end: '00:01:500', state: 'medium' },
        { start: '00:01:500', end: '00:03:000', state: 'idle' },
        { start: '00:03:000', end: '00:05:000', state: 'slow' },
        { start: '00:05:000', end: '00:06:000', state: 'idle' },
        { start: '00:06:000', end: '00:18:900', state: 'medium' }, // Begin Peak --
        { start: '00:18:900', end: '00:30:000', state: 'fast' },
        { start: '00:30:000', end: '00:31:700', state: 'idle' },        
        { start: '00:31:700', end: '00:34:500', state: 'fast' },        
        { start: '00:34:500', end: '00:34:900', state: 'idle' },        
        { start: '00:34:900', end: '00:37:600', state: 'fast' },        
        { start: '00:37:600', end: '00:38:200', state: 'idle' },        
        { start: '00:38:200', end: '00:42:900', state: 'fast' }, // -- End Peak 
        { start: '00:42:900', end: '00:44:500', state: 'idle' },
        { start: '00:44:500', end: '00:56:900', state: 'medium' }, // Finishing, cat di tengah layar & membesar warna-warni
        { start: '00:56:900', end: '00:57:300', state: 'idle' },   
        { start: '00:57:300', end: '01:00:500', state: 'fast' } // Ending, meledak di detik 59:500
    ];

    // --------------------------------------------------------------------------------
    // 3. ELEMEN DOM & REFERENSI
    // --------------------------------------------------------------------------------
    const catContainer    = document.getElementById('oiiaCatContainer');
    const imgIdle         = document.getElementById('oiiaCatIdle');
    const imgSlow         = document.getElementById('oiiaCatSlow');
    const imgMedium       = document.getElementById('oiiaCatMedium');
    const imgFast         = document.getElementById('oiiaCatFast');
    const audio           = document.getElementById('oiiaCatAudio');
    const flashOverlay    = document.getElementById('oiiaFlashOverlay');
    const strobeOverlay   = document.getElementById('oiiaStrobeOverlay');
    const explosionCanvas = document.getElementById('oiiaExplosionCanvas');

    // Elemen Halaman Traktir untuk Diberikan Efek Chaos & Jedag-Jedug
    const pageContainer   = document.getElementById('traktirPageContainer');
    const cardElement     = pageContainer ? pageContainer.querySelector('.card') : null;
    const qrisImage       = pageContainer ? pageContainer.querySelector('img[src*="qris"]') : null;
    const qrIconCircle    = pageContainer ? pageContainer.querySelector('.rounded-circle') : null;
    const qrIconInside    = qrIconCircle ? qrIconCircle.querySelector('i') : null;
    const headerTitle     = pageContainer ? pageContainer.querySelector('h4') : null;
    const headerIcon      = headerTitle ? headerTitle.querySelector('i') : null;
    const headerSub       = pageContainer ? pageContainer.querySelector('p.text-secondary') : null;
    const qrisTitle       = pageContainer ? pageContainer.querySelector('h5') : null;
    const downloadBtn     = pageContainer ? pageContainer.querySelector('.btn') : null;
    const thankYouBox     = pageContainer ? pageContainer.querySelector('.bg-light') : null;

    if (!catContainer || !audio) return;

    // --------------------------------------------------------------------------------
    // 4. PARSER WAKTU & DETEKTOR STAGE
    // --------------------------------------------------------------------------------
    function parseTimestamp(val) {
        if (typeof val === 'number') return val;
        if (typeof val !== 'string') return 0;
        const parts = val.trim().split(':');
        if (parts.length === 3) {
            const mins = parseFloat(parts[0]) || 0;
            const secs = parseFloat(parts[1]) || 0;
            let ms = parseFloat(parts[2]) || 0;
            if (parts[2].length <= 2) {
                ms = ms / Math.pow(10, parts[2].length);
            } else {
                ms = ms / 1000;
            }
            return mins * 60 + secs + ms;
        } else if (parts.length === 2) {
            return (parseFloat(parts[0]) || 0) * 60 + (parseFloat(parts[1]) || 0);
        }
        return parseFloat(val) || 0;
    }

    const PEAK_START_TIME      = parseTimestamp('00:06:000');
    const PEAK_END_TIME        = parseTimestamp('00:42:900');
    const FINISHING_START_TIME = parseTimestamp('00:44:500');
    const FINISHING_END_TIME   = parseTimestamp('00:56:900');
    const ENDING_START_TIME    = parseTimestamp('00:57:300');
    const EXPLOSION_TIME       = parseTimestamp(OIIA_CONFIG.explosionTime);

    function getStage(t) {
        if (t < PEAK_START_TIME) return 'PRE_PEAK';
        if (t >= PEAK_START_TIME && t < FINISHING_START_TIME) return 'PEAK';
        if (t >= FINISHING_START_TIME && t < ENDING_START_TIME) return 'FINISHING';
        if (t >= ENDING_START_TIME) return 'ENDING';
        return 'PRE_PEAK';
    }

    function getStateForTime(t) {
        for (const item of OIIA_TIMELINE) {
            const start = parseTimestamp(item.start);
            const end   = parseTimestamp(item.end);
            if (t >= start && t < end) {
                return item.state;
            }
        }
        return 'idle';
    }

    // Helper angka acak dalam rentang [min, max]
    function randomRange(min, max) {
        return min + Math.random() * (max - min);
    }

    // --------------------------------------------------------------------------------
    // 5. STATUS KUCING & TRANSISI GAMBAR
    // --------------------------------------------------------------------------------
    let currentCatState = 'idle';

    function setCatState(state) {
        if (currentCatState === state) return;
        currentCatState = state;

        if (imgIdle)   imgIdle.classList.toggle('active', state === 'idle');
        if (imgSlow)   imgSlow.classList.toggle('active', state === 'slow');
        if (imgMedium) imgMedium.classList.toggle('active', state === 'medium');
        if (imgFast)   imgFast.classList.toggle('active', state === 'fast');
    }

    let bounceTimeout = null;
    function triggerBounce() {
        catBeatScale = 1.35; // Langsung picu hentakan scale kucing saat diklik
        if (bounceTimeout) clearTimeout(bounceTimeout);
        catContainer.classList.remove('cat-bounce');
        void catContainer.offsetWidth; // Force reflow
        catContainer.classList.add('cat-bounce');
        bounceTimeout = setTimeout(() => {
            catContainer.classList.remove('cat-bounce');
            bounceTimeout = null;
        }, 110);
    }

    // --------------------------------------------------------------------------------
    // 6. DVD BOUNCE PHYSICS ENGINE (MANTUL-MANTUL DVD CEPAT & RESPONSIF)
    // --------------------------------------------------------------------------------
    let dvdX = 0;
    let dvdY = 0;
    let dvdVx = 0;
    let dvdVy = 0;
    let dvdInitialized = false;

    // Variabel spring jedag-jedug scale & squash pantulan tepi layar
    let catBeatScale  = 1.0;
    let borderSquashX = 1.0;
    let borderSquashY = 1.0;

    function initDvdPosition() {
        const rect = catContainer.getBoundingClientRect();
        dvdX = rect.left;
        dvdY = rect.top;
        const initialSpeed = OIIA_CONFIG.dvdSpeedMedium;
        dvdVx = initialSpeed;
        dvdVy = -initialSpeed * 0.85;
        dvdInitialized = true;
        catContainer.style.bottom     = 'auto';
        catContainer.style.right      = 'auto';
        catContainer.style.left       = `${dvdX}px`;
        catContainer.style.top        = `${dvdY}px`;
        catContainer.style.transition = 'none'; // Matikan transisi CSS agar pergerakan 60fps bebas lag
    }

    function triggerBorderImpact() {
        if (cardElement) {
            const jolt = (Math.random() - 0.5) * 8;
            cardElement.style.transform += ` translate(${jolt.toFixed(1)}px, ${jolt.toFixed(1)}px)`;
        }
    }

    function updateDvdBounce(speed, state) {
        if (!dvdInitialized) {
            initDvdPosition();
        }

        // Sesuaikan besar vektor kecepatan secara dinamis (Medium vs Fast)
        const currentSpeed = Math.sqrt(dvdVx * dvdVx + dvdVy * dvdVy) || 1;
        dvdVx = (dvdVx / currentSpeed) * speed;
        dvdVy = (dvdVy / currentSpeed) * speed;

        dvdX += dvdVx;
        dvdY += dvdVy;

        const catWidth  = catContainer.offsetWidth  || 175;
        const catHeight = catContainer.offsetHeight || 175;
        const maxX = Math.max(0, window.innerWidth - catWidth);
        const maxY = Math.max(0, window.innerHeight - catHeight);

        let hitBorder = false;

        // Pantulan horizontal (kiri / kanan)
        if (dvdX <= 0) {
            dvdX = 0;
            dvdVx = Math.abs(dvdVx);
            hitBorder = true;
            borderSquashX = 0.80; // Gepeng saat menabrak dinding horizontal
            borderSquashY = 1.20;
        } else if (dvdX >= maxX) {
            dvdX = maxX;
            dvdVx = -Math.abs(dvdVx);
            hitBorder = true;
            borderSquashX = 0.80;
            borderSquashY = 1.20;
        }

        // Pantulan vertikal (atas / bawah)
        if (dvdY <= 0) {
            dvdY = 0;
            dvdVy = Math.abs(dvdVy);
            hitBorder = true;
            borderSquashX = 1.20; // Gepeng saat menabrak dinding vertikal
            borderSquashY = 0.80;
        } else if (dvdY >= maxY) {
            dvdY = maxY;
            dvdVy = -Math.abs(dvdVy);
            hitBorder = true;
            borderSquashX = 1.20;
            borderSquashY = 0.80;
        }

        // Terapkan posisi dan efek scale jedag-jedug ke kucing
        const finalScaleX = catBeatScale * borderSquashX;
        const finalScaleY = catBeatScale * borderSquashY;

        catContainer.style.left            = `${dvdX}px`;
        catContainer.style.top             = `${dvdY}px`;
        catContainer.style.transform       = `scale(${finalScaleX.toFixed(3)}, ${finalScaleY.toFixed(3)})`;
        catContainer.style.transformOrigin = 'center center';

        // Pendaran turbo saat varian fast
        if (state === 'fast') {
            catContainer.style.filter = 'drop-shadow(0 0 18px rgba(255, 0, 85, 0.75)) drop-shadow(0 10px 22px rgba(0, 0, 0, 0.35))';
        } else {
            catContainer.style.filter = 'drop-shadow(0 10px 22px rgba(0, 0, 0, 0.28))';
        }

        if (hitBorder) {
            triggerBorderImpact();
        }
    }

    // --------------------------------------------------------------------------------
    // 7. BEAT & BACKGROUND CHAOS ENGINE (JEDAG-JEDUG SUPER HEBOH)
    // --------------------------------------------------------------------------------
    let lastBeatIndex  = -1;
    let decayTimeoutId = null;

    function triggerWindowShake(mode, isIntense) {
        const body = document.body;
        body.classList.remove('oiia-screen-shake', 'oiia-screen-shake-slam', 'oiia-screen-shake-twist', 'oiia-screen-shake-crazy');
        void body.offsetWidth; // Force reflow

        if (isIntense || mode === 4) {
            body.classList.add('oiia-screen-shake-crazy');
        } else if (mode === 0 || mode === 2) {
            body.classList.add('oiia-screen-shake-slam');
        } else {
            body.classList.add('oiia-screen-shake-twist');
        }
    }

    function triggerStrobeFlash() {
        if (!strobeOverlay || !OIIA_CONFIG.enableStrobeFlash) return;
        const hue = Math.floor(Math.random() * 360);
        strobeOverlay.style.background = `hsla(${hue}, 100%, 50%, 0.16)`;
        strobeOverlay.style.opacity = '1';
        setTimeout(() => {
            if (strobeOverlay) strobeOverlay.style.opacity = '0';
        }, 55);
    }

    function applyElementsChaos(mode, intensity, isFastOrEnding) {
        const tiltMax   = OIIA_CONFIG.tiltAngleMax * intensity;
        const qrisMax   = OIIA_CONFIG.qrisTiltMax * intensity;
        const bumpMax   = OIIA_CONFIG.scaleBumpMax * intensity;
        const scMax     = OIIA_CONFIG.scatterDistanceMax * intensity;

        const dir       = (Math.random() > 0.5) ? 1 : -1;
        const hue       = Math.floor(Math.random() * 360);
        const bumpScale = 1 + (Math.random() * bumpMax);

        switch (mode) {
            case 0:
                // =============================================================
                // MODE 0: BASS SLAM & SQUASH (Hentakan Vertikal Gepeng)
                // =============================================================
                if (cardElement) {
                    cardElement.style.transform = `translateY(${14 * intensity}px) scale(${(1 + bumpMax * 1.1).toFixed(3)}, ${(1 - bumpMax * 0.45).toFixed(3)})`;
                    cardElement.style.boxShadow = `0 18px 35px rgba(0, 0, 0, 0.18)`;
                }
                if (qrisImage) {
                    qrisImage.style.transform = `translateY(${-12 * intensity}px) scale(${(1.16 + bumpMax).toFixed(3)})`;
                    qrisImage.style.filter    = `drop-shadow(0 0 20px rgba(13, 110, 253, 0.8)) saturate(2.2) contrast(1.2)`;
                }
                if (headerTitle) {
                    headerTitle.style.transform = `translateY(${-10 * intensity}px) scale(1.04)`;
                }
                if (qrIconCircle) {
                    qrIconCircle.style.transform = `scale(1.35) rotate(${dir * 18}deg)`;
                }
                if (downloadBtn) {
                    downloadBtn.style.transform = `translateY(${8 * intensity}px) scale(1.12)`;
                }
                if (thankYouBox) {
                    thankYouBox.style.transform = `translateY(${8 * intensity}px)`;
                }
                break;

            case 1:
                // =============================================================
                // MODE 1: VIOLENT TWIST (Puntiran & Rotasi Silang)
                // =============================================================
                if (cardElement) {
                    cardElement.style.transform = `rotate(${(dir * tiltMax).toFixed(1)}deg) scale(${bumpScale.toFixed(3)})`;
                }
                if (qrisImage) {
                    qrisImage.style.transform = `rotate(${(-dir * qrisMax).toFixed(1)}deg) scale(${(1.14 + bumpMax).toFixed(3)})`;
                    qrisImage.style.filter    = `hue-rotate(${hue}deg) saturate(2.5)`;
                }
                if (qrIconCircle) {
                    qrIconCircle.style.transform = `rotate(${(dir * 40).toFixed(1)}deg) scale(1.28)`;
                }
                if (headerTitle) {
                    headerTitle.style.transform = `skewX(${(dir * 10).toFixed(1)}deg) rotate(${(dir * 4).toFixed(1)}deg) translateX(${(dir * 14).toFixed(1)}px)`;
                }
                if (downloadBtn) {
                    downloadBtn.style.transform = `rotate(${(-dir * 15).toFixed(1)}deg) translateX(${(dir * 12).toFixed(1)}px) scale(1.08)`;
                }
                if (thankYouBox) {
                    thankYouBox.style.transform = `skewX(${(-dir * 8).toFixed(1)}deg)`;
                }
                break;

            case 2:
                // =============================================================
                // MODE 2: DISCO STROBE & COLOR EXPLOSION (Warna-Warni Disko)
                // =============================================================
                triggerStrobeFlash();

                if (cardElement) {
                    cardElement.style.transform = `rotate(${(dir * tiltMax * 0.5).toFixed(1)}deg) scale(${bumpScale.toFixed(3)})`;
                    cardElement.style.boxShadow = `0 0 45px hsl(${hue}, 100%, 65%), 0 10px 25px rgba(0, 0, 0, 0.25)`;
                }
                if (qrisImage) {
                    qrisImage.style.transform = `scale(${(1.18 + bumpMax).toFixed(3)})`;
                    qrisImage.style.filter    = `hue-rotate(${hue}deg) saturate(3.2) contrast(1.35) drop-shadow(0 0 18px hsla(${hue}, 100%, 50%, 0.8))`;
                }
                if (qrIconCircle) {
                    qrIconCircle.style.transform = `scale(1.32) rotate(${dir * 25}deg)`;
                    qrIconCircle.style.boxShadow = `0 0 25px hsl(${(hue + 120) % 360}, 100%, 60%)`;
                }
                if (headerTitle) {
                    headerTitle.style.textShadow = `0 0 18px hsl(${hue}, 100%, 50%)`;
                    headerTitle.style.transform  = `scale(1.05)`;
                }
                if (downloadBtn) {
                    downloadBtn.style.filter    = `hue-rotate(${hue}deg)`;
                    downloadBtn.style.transform = `scale(1.15)`;
                }
                break;

            case 3:
                // =============================================================
                // MODE 3: EARTHQUAKE SCATTER GLITCH (Div-Div Berhamburan Acak)
                // =============================================================
                if (headerTitle) {
                    headerTitle.style.transform = `translate(${randomRange(-scMax, scMax).toFixed(1)}px, ${randomRange(-scMax * 0.7, scMax * 0.7).toFixed(1)}px) rotate(${randomRange(-8, 8).toFixed(1)}deg)`;
                }
                if (headerIcon) {
                    headerIcon.style.transform = `scale(${randomRange(1.2, 1.5).toFixed(2)}) rotate(${randomRange(-30, 30).toFixed(1)}deg)`;
                }
                if (headerSub) {
                    headerSub.style.transform = `translate(${randomRange(-scMax * 0.6, scMax * 0.6).toFixed(1)}px, ${randomRange(-scMax * 0.4, scMax * 0.4).toFixed(1)}px)`;
                }
                if (cardElement) {
                    cardElement.style.transform = `translate(${randomRange(-scMax, scMax).toFixed(1)}px, ${randomRange(-scMax * 0.8, scMax * 0.8).toFixed(1)}px) rotate(${randomRange(-tiltMax * 0.8, tiltMax * 0.8).toFixed(1)}deg) scale(${bumpScale.toFixed(3)})`;
                }
                if (qrisImage) {
                    qrisImage.style.transform = `translate(${randomRange(-scMax * 1.2, scMax * 1.2).toFixed(1)}px, ${randomRange(-scMax, scMax).toFixed(1)}px) rotate(${randomRange(-qrisMax * 0.9, qrisMax * 0.9).toFixed(1)}deg) scale(${(1.15 + bumpMax).toFixed(3)})`;
                    qrisImage.style.filter    = `hue-rotate(${hue}deg) saturate(2.2)`;
                }
                if (qrIconCircle) {
                    qrIconCircle.style.transform = `translate(${randomRange(-scMax * 1.3, scMax * 1.3).toFixed(1)}px, ${randomRange(-scMax, scMax).toFixed(1)}px) rotate(${randomRange(-45, 45).toFixed(1)}deg) scale(1.35)`;
                }
                if (qrisTitle) {
                    qrisTitle.style.transform = `translate(${randomRange(-scMax * 0.8, scMax * 0.8).toFixed(1)}px, ${randomRange(-scMax * 0.5, scMax * 0.5).toFixed(1)}px)`;
                }
                if (downloadBtn) {
                    downloadBtn.style.transform = `translate(${randomRange(-scMax, scMax).toFixed(1)}px, ${randomRange(-scMax * 0.8, scMax * 0.8).toFixed(1)}px) rotate(${randomRange(-14, 14).toFixed(1)}deg) scale(1.12)`;
                }
                if (thankYouBox) {
                    thankYouBox.style.transform = `translate(${randomRange(-scMax * 0.7, scMax * 0.7).toFixed(1)}px, ${randomRange(-scMax * 0.5, scMax * 0.5).toFixed(1)}px) skewX(${randomRange(-10, 10).toFixed(1)}deg)`;
                }
                break;

            case 4:
            default:
                // =============================================================
                // MODE 4: SUPER OVERDRIVE COMBO (Kombinasi Semua Efek Puncak)
                // =============================================================
                triggerStrobeFlash();

                if (cardElement) {
                    cardElement.style.transform = `perspective(700px) rotateX(${(dir * 12).toFixed(1)}deg) rotateY(${(-dir * 14).toFixed(1)}deg) scale(${(bumpScale * 1.05).toFixed(3)})`;
                    cardElement.style.boxShadow = `0 0 55px hsl(${hue}, 100%, 65%), 0 15px 35px rgba(0, 0, 0, 0.3)`;
                }
                if (qrisImage) {
                    qrisImage.style.transform = `rotate(${(dir * qrisMax).toFixed(1)}deg) scale(${(1.22 + bumpMax).toFixed(3)})`;
                    qrisImage.style.filter    = `hue-rotate(${hue}deg) saturate(3.5) contrast(1.4) drop-shadow(0 0 24px hsla(${hue}, 100%, 55%, 0.9))`;
                }
                if (qrIconCircle) {
                    qrIconCircle.style.transform = `rotate(${(-dir * 55).toFixed(1)}deg) scale(1.4)`;
                }
                if (headerTitle) {
                    headerTitle.style.transform  = `translate(${(dir * 18).toFixed(1)}px, ${(-12 * intensity).toFixed(1)}px) scale(1.08)`;
                    headerTitle.style.textShadow = `0 0 22px hsl(${hue}, 100%, 50%)`;
                }
                if (headerIcon) {
                    headerIcon.style.transform = `scale(1.5) rotate(${dir * 35}deg)`;
                }
                if (downloadBtn) {
                    downloadBtn.style.transform = `rotate(${(dir * 16).toFixed(1)}deg) scale(1.2)`;
                    downloadBtn.style.filter    = `hue-rotate(${hue}deg)`;
                }
                if (thankYouBox) {
                    thankYouBox.style.transform = `translateY(${12 * intensity}px) skewX(${(dir * 10).toFixed(1)}deg)`;
                }
                break;
        }
    }

    function decayElementsToRest() {
        // Redam sebagian transformasi elemen sebelum ketukan berikutnya datang
        if (cardElement) {
            cardElement.style.transform = 'scale(1.01)';
            cardElement.style.boxShadow = '';
        }
        if (qrisImage) {
            qrisImage.style.transform = 'scale(1.02)';
            qrisImage.style.filter    = '';
        }
        if (qrIconCircle) {
            qrIconCircle.style.transform = 'scale(1.05)';
            qrIconCircle.style.boxShadow = '';
        }
        if (headerTitle) {
            headerTitle.style.transform  = '';
            headerTitle.style.textShadow = '';
        }
        if (headerIcon)   headerIcon.style.transform   = '';
        if (headerSub)    headerSub.style.transform    = '';
        if (qrisTitle)    qrisTitle.style.transform    = '';
        if (downloadBtn) {
            downloadBtn.style.transform = 'scale(1.02)';
            downloadBtn.style.filter    = '';
        }
        if (thankYouBox)  thankYouBox.style.transform  = '';
    }

    function resetBackgroundChaos() {
        // Hapus seluruh kelas guncangan layar
        document.body.classList.remove('oiia-screen-shake', 'oiia-screen-shake-slam', 'oiia-screen-shake-twist', 'oiia-screen-shake-crazy');

        if (decayTimeoutId) {
            clearTimeout(decayTimeoutId);
            decayTimeoutId = null;
        }

        if (strobeOverlay) {
            strobeOverlay.style.opacity = '0';
        }

        // Kembalikan seluruh elemen latar ke gaya bawaan CSS yang bersih
        if (cardElement) {
            cardElement.style.transform = '';
            cardElement.style.boxShadow = '';
        }
        if (qrisImage) {
            qrisImage.style.transform = '';
            qrisImage.style.filter    = '';
        }
        if (qrIconCircle) {
            qrIconCircle.style.transform = '';
            qrIconCircle.style.boxShadow = '';
        }
        if (headerTitle) {
            headerTitle.style.transform  = '';
            headerTitle.style.textShadow = '';
        }
        if (headerIcon)   headerIcon.style.transform   = '';
        if (headerSub)    headerSub.style.transform    = '';
        if (qrisTitle)    qrisTitle.style.transform    = '';
        if (downloadBtn) {
            downloadBtn.style.transform = '';
            downloadBtn.style.filter    = '';
        }
        if (thankYouBox)  thankYouBox.style.transform  = '';
        if (pageContainer) {
            pageContainer.style.filter    = '';
            pageContainer.style.transform = '';
        }
    }

    function checkBeatTrigger(t, state, stage) {
        if (state === 'idle' || stage === 'PRE_PEAK') {
            return;
        }

        // Hitung interval beat berdasarkan BPM
        const tempoBpm = OIIA_CONFIG.bpm || 147;
        const baseInterval = 60 / tempoBpm;

        // Saat varian 'fast', gunakan ketukan 2x lipat (sub-beat) agar jedag-jedug semakin padat & heboh
        const currentInterval = (state === 'fast') ? (baseInterval / 2) : baseInterval;

        const currentBeat = Math.floor(t / currentInterval);
        if (currentBeat !== lastBeatIndex) {
            lastBeatIndex = currentBeat;

            const intensity = OIIA_CONFIG.intensityMultiplier || 1.0;
            const isFast    = (state === 'fast');
            const isEnding  = (stage === 'ENDING');

            // 1. PICU JEDAG-JEDUG SCALE PADA KUCING!
            const catBump = isFast ? (OIIA_CONFIG.catScaleBump * 1.35) : OIIA_CONFIG.catScaleBump;
            catBeatScale = 1.0 + (catBump * intensity);

            // 2. Variasikan mode bergantian untuk elemen latar (0 s/d 4)
            const mode = isFast ? (currentBeat % 2 === 0 ? 4 : (currentBeat % 4)) : (currentBeat % 5);

            triggerWindowShake(mode, isFast || isEnding);
            applyElementsChaos(mode, intensity, isFast || isEnding);

            // Jadwalkan redam halus (decay) untuk elemen latar
            const decayDelay = isFast ? 75 : 115;
            if (decayTimeoutId) clearTimeout(decayTimeoutId);
            decayTimeoutId = setTimeout(() => {
                decayElementsToRest();
            }, decayDelay);
        }
    }

    // --------------------------------------------------------------------------------
    // 8. EXPLOSION PARTICLE ENGINE (CANVAS)
    // --------------------------------------------------------------------------------
    let particles = [];
    let explosionAnimId = null;
    let hasExploded = false;

    function runExplosion() {
        if (!explosionCanvas) return;

        // Flash kilat putih layar penuh
        if (flashOverlay) {
            flashOverlay.style.opacity = '1';
            setTimeout(() => {
                if (flashOverlay) flashOverlay.style.opacity = '0';
            }, 120);
        }

        // Kucing meledak & hilang
        catContainer.style.filter    = 'brightness(5) blur(12px)';
        catContainer.style.transform = 'translate(-50%, -50%) scale(4.5)';
        catContainer.style.opacity   = '0';

        // Siapkan Canvas Partikel Layar Penuh
        explosionCanvas.width  = window.innerWidth;
        explosionCanvas.height = window.innerHeight;
        explosionCanvas.style.display = 'block';

        const ctx = explosionCanvas.getContext('2d');
        const centerX = window.innerWidth / 2;
        const centerY = window.innerHeight / 2;

        particles = [];
        const colors = ['#ff0055', '#ff9900', '#ffee00', '#00ffcc', '#0099ff', '#cc00ff', '#ffffff'];
        const totalParticles = OIIA_CONFIG.particleCount || 120;

        for (let i = 0; i < totalParticles; i++) {
            const angle = Math.random() * Math.PI * 2;
            const speed = 4 + Math.random() * 24;
            particles.push({
                x: centerX,
                y: centerY,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed,
                size: 6 + Math.random() * 20,
                color: colors[Math.floor(Math.random() * colors.length)],
                alpha: 1,
                decay: 0.012 + Math.random() * 0.02,
                rotation: Math.random() * Math.PI,
                vRot: (Math.random() - 0.5) * 0.3,
                isCircle: Math.random() > 0.45
            });
        }

        function drawExplosion() {
            ctx.clearRect(0, 0, explosionCanvas.width, explosionCanvas.height);
            let activeCount = 0;

            for (const p of particles) {
                if (p.alpha <= 0) continue;
                activeCount++;

                p.x  += p.vx;
                p.y  += p.vy;
                p.vy += 0.24; // Gravitasi serpihan
                p.rotation += p.vRot;
                p.alpha    -= p.decay;

                ctx.save();
                ctx.globalAlpha = Math.max(0, p.alpha);
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rotation);
                ctx.fillStyle = p.color;

                if (p.isCircle) {
                    ctx.beginPath();
                    ctx.arc(0, 0, p.size / 2, 0, Math.PI * 2);
                    ctx.fill();
                } else {
                    ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size);
                }
                ctx.restore();
            }

            if (activeCount > 0) {
                explosionAnimId = requestAnimationFrame(drawExplosion);
            } else {
                explosionCanvas.style.display = 'none';
                explosionAnimId = null;
            }
        }

        explosionAnimId = requestAnimationFrame(drawExplosion);
    }

    function clearExplosion() {
        if (explosionAnimId) {
            cancelAnimationFrame(explosionAnimId);
            explosionAnimId = null;
        }
        if (explosionCanvas) {
            const ctx = explosionCanvas.getContext('2d');
            ctx.clearRect(0, 0, explosionCanvas.width, explosionCanvas.height);
            explosionCanvas.style.display = 'none';
        }
        if (flashOverlay) {
            flashOverlay.style.opacity = '0';
        }
        hasExploded = false;
    }

    // --------------------------------------------------------------------------------
    // 9. LOOP UTAMA (TICKER ENGINE)
    // --------------------------------------------------------------------------------
    let isFullMode   = false;
    let stopDeadline = 0;
    let animTickerId = null;

    function tick() {
        if (audio.paused) {
            animTickerId = null;
            return;
        }

        const t     = audio.currentTime;
        const stage = getStage(t);
        const state = getStateForTime(t);

        // A. Cek Syarat Spam Klik (>= 5.0 detik untuk Full Play Mode)
        if (!isFullMode && t >= 5.0) {
            isFullMode = true;
        }

        // B. Batas Waktu Non-Full Mode (0.5s per klik)
        if (!isFullMode && t >= stopDeadline) {
            stopAndReset();
            return;
        }

        // C. Perbarui Varian Kucing Aktif Sesuai Timeline
        setCatState(state);

        // D. Redam halus (spring decay) scale jedag-jedug kucing & squash dinding ke 1.0
        if (state === 'idle') {
            catBeatScale  = 1.0;
            borderSquashX = 1.0;
            borderSquashY = 1.0;
        } else {
            const decaySpeed = (state === 'fast') ? 0.32 : 0.22;
            catBeatScale  += (1.0 - catBeatScale) * decaySpeed;
            borderSquashX += (1.0 - borderSquashX) * 0.25;
            borderSquashY += (1.0 - borderSquashY) * 0.25;
        }

        // E. Eksekusi Efek Sesuai Kondisi State & Stage
        if (state === 'idle') {
            // =========================================================================
            // KONDISI IDLE: SEMUA EFEK HILANG & KUCING DIAM SEBENTAR
            // =========================================================================
            resetBackgroundChaos();

            // Jika sedang dalam masa mantul DVD, kucing membeku di koordinat terakhirnya
            if (dvdInitialized && stage === 'PEAK') {
                catContainer.style.left      = `${dvdX}px`;
                catContainer.style.top       = `${dvdY}px`;
                catContainer.style.transform = 'scale(1, 1)';
                catContainer.style.filter    = 'drop-shadow(0 10px 22px rgba(0, 0, 0, 0.28))';
            }
        } else if (stage === 'PEAK') {
            // =========================================================================
            // TAHAP PEAK (00:06:000 - 00:42:900): MANTUL DVD + JEDAG-JEDUG BEAT
            // =========================================================================
            const currentSpeed = (state === 'fast') ? OIIA_CONFIG.dvdSpeedFast : OIIA_CONFIG.dvdSpeedMedium;
            updateDvdBounce(currentSpeed, state);
            checkBeatTrigger(t, state, stage);

        } else if (stage === 'FINISHING') {
            // =========================================================================
            // TAHAP FINISHING (00:44:500 - 00:56:900): TENGAH LAYAR & MEMBESAR WARNA-WARNI
            // =========================================================================
            dvdInitialized = false;

            const progress = Math.min(1, Math.max(0, (t - FINISHING_START_TIME) / (FINISHING_END_TIME - FINISHING_START_TIME)));
            const scale    = OIIA_CONFIG.finishScaleStart + progress * (OIIA_CONFIG.finishScaleEnd - OIIA_CONFIG.finishScaleStart);
            const hue      = Math.floor((t * 280) % 360);

            // Terapkan scale beat jedag-jedug ke kucing saat finishing
            const finalScale = scale * catBeatScale;

            catContainer.style.left      = '50%';
            catContainer.style.top       = '50%';
            catContainer.style.bottom    = 'auto';
            catContainer.style.right     = 'auto';
            catContainer.style.transform = `translate(-50%, -50%) scale(${finalScale.toFixed(3)})`;
            catContainer.style.filter    = `drop-shadow(0 0 ${(24 + progress * 40).toFixed(0)}px hsl(${hue}, 100%, 60%)) hue-rotate(${hue}deg)`;

            // Efek jedag-jedug tetap aktif mengikuti tempo lagu selama bukan idle
            checkBeatTrigger(t, state, stage);

        } else if (stage === 'ENDING') {
            // =========================================================================
            // TAHAP ENDING (00:57:300 - 01:00:500): KLIMAKS PUTARAN & MELEDAK DI 00:59:500
            // =========================================================================
            dvdInitialized = false;

            if (t >= EXPLOSION_TIME && !hasExploded) {
                hasExploded = true;
                runExplosion();
                resetBackgroundChaos();
            } else if (!hasExploded) {
                const finalScale = 1.75 * catBeatScale;
                catContainer.style.left      = '50%';
                catContainer.style.top       = '50%';
                catContainer.style.bottom    = 'auto';
                catContainer.style.right     = 'auto';
                catContainer.style.transform = `translate(-50%, -50%) scale(${finalScale.toFixed(3)})`;
                catContainer.style.filter    = 'drop-shadow(0 10px 25px rgba(0, 0, 0, 0.35))';
                catContainer.style.opacity   = '1';

                // Klimaks efek sebelum ledakan
                checkBeatTrigger(t, state, stage);
            }

        } else {
            // =========================================================================
            // TAHAP PRE_PEAK (00:00:100 - 00:06:000): DIAM DI POJOK KANAN BAWAH
            // =========================================================================
            resetBackgroundChaos();
            dvdInitialized = false;
        }

        animTickerId = requestAnimationFrame(tick);
    }

    // --------------------------------------------------------------------------------
    // 10. RESET BERSIH KE STATUS AWAL
    // --------------------------------------------------------------------------------
    function stopAndReset() {
        audio.pause();
        audio.currentTime = 0;
        isFullMode        = false;
        stopDeadline      = 0;
        dvdInitialized    = false;
        lastBeatIndex     = -1;
        catBeatScale      = 1.0;
        borderSquashX     = 1.0;
        borderSquashY     = 1.0;

        clearExplosion();
        resetBackgroundChaos();
        setCatState('idle');

        // Kembalikan kucing ke posisi default di pojok kanan bawah
        catContainer.style.cssText = '';

        if (animTickerId) {
            cancelAnimationFrame(animTickerId);
            animTickerId = null;
        }
    }

    audio.addEventListener('ended', () => {
        stopAndReset();
    });

    // --------------------------------------------------------------------------------
    // 11. EVENT LISTENER KLIK KUCING
    // --------------------------------------------------------------------------------
    catContainer.addEventListener('click', (e) => {
        e.preventDefault();
        triggerBounce();

        if (audio.paused) {
            audio.play().catch(err => {
                console.warn('Autoplay audio dicegah oleh browser:', err);
            });
        }

        if (!isFullMode) {
            stopDeadline = audio.currentTime + 0.5;
        }

        if (!animTickerId) {
            animTickerId = requestAnimationFrame(tick);
        }
    });
});
