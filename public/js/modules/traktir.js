/**
 * ====================================================================================
 * MODULE: Oiia Cat Easter Egg Controller
 * FILE LOCATION: public/js/modules/traktir.js
 * ====================================================================================
 * 
 * Deskripsi:
 * Mengontrol animasi dan sinkronisasi audio meme Oiia Cat pada halaman traktir.
 * Mendukung konfigurasi timeline pergantian varian kucing (idle, slow, medium, fast),
 * interaksi hover, click bounce, spam tracking, dan unlock Full Play Mode (> 5 detik).
 */

document.addEventListener('DOMContentLoaded', () => {
    const catContainer = document.getElementById('oiiaCatContainer');
    const imgIdle      = document.getElementById('oiiaCatIdle');
    const imgSlow      = document.getElementById('oiiaCatSlow');
    const imgMedium    = document.getElementById('oiiaCatMedium');
    const imgFast      = document.getElementById('oiiaCatFast');
    const audio        = document.getElementById('oiiaCatAudio');

    if (!catContainer || !audio) return;

    /**
     * Konfigurasi Timeline Pergantian Varian Kucing.
     * Format timestamp dapat berupa string "MM:SS:mmm" atau angka detik murni.
     * Varian state: 'idle', 'slow', 'medium', 'fast'.
     */
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
        { start: '00:44:500', end: '00:56:900', state: 'medium' }, // Finishing, cat di tengah layar dan semakin membesar dengan efek warna-warni
        { start: '00:56:900', end: '00:57:300', state: 'idle' },   
        { start: '00:57:300', end: '01:00:500', state: 'fast' } // Ending, putar di tengah aja tanpa efek. Di detik ke 59:500 buat efek ledakan, cat juga meledak
    ];

    /**
     * Parsing timestamp ke nilai desimal detik murni.
     * Mendukung: "MM:SS:mmm", "MM:SS.mmm", "MM:SS", atau angka detik.
     */
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

    /**
     * Menentukan varian state berdasarkan detik audio saat ini.
     */
    function getStateForTime(t) {
        for (const item of OIIA_TIMELINE) {
            const start = parseTimestamp(item.start);
            const end = parseTimestamp(item.end);
            if (t >= start && t < end) {
                return item.state;
            }
        }
        return 'idle';
    }

    let currentCatState = 'idle';

    /**
     * Mengganti tampilan gambar kucing aktif.
     */
    function setCatState(state) {
        if (currentCatState === state) return;
        currentCatState = state;

        if (imgIdle)   imgIdle.classList.toggle('active', state === 'idle');
        if (imgSlow)   imgSlow.classList.toggle('active', state === 'slow');
        if (imgMedium) imgMedium.classList.toggle('active', state === 'medium');
        if (imgFast)   imgFast.classList.toggle('active', state === 'fast');
    }

    let bounceTimeout = null;

    /**
     * Efek visual klik: scale up membal selama 0.1 detik untuk seluruh varian (idle & spin).
     */
    function triggerBounce() {
        if (bounceTimeout) {
            clearTimeout(bounceTimeout);
        }
        catContainer.classList.remove('cat-bounce');
        void catContainer.offsetWidth; // Trigger reflow untuk reset animasi
        catContainer.classList.add('cat-bounce');
        bounceTimeout = setTimeout(() => {
            catContainer.classList.remove('cat-bounce');
            bounceTimeout = null;
        }, 110);
    }

    let isFullMode = false;
    let stopDeadline = 0;
    let animTickerId = null;

    /**
     * Loop ticker presisi tinggi menggunakan requestAnimationFrame.
     */
    function tick() {
        if (audio.paused) {
            animTickerId = null;
            return;
        }

        const t = audio.currentTime;

        // Cek syarat spam: Jika pemutaran aktif mencapai >= 5 detik, buka Full Mode
        if (!isFullMode && t >= 5.0) {
            isFullMode = true;
        }

        // Jika bukan Full Mode dan sudah melewati batas waktu (0.5 detik dari klik terakhir)
        if (!isFullMode && t >= stopDeadline) {
            stopAndReset();
            return;
        }

        // Sinkronkan varian gambar sesuai detik lagu
        const targetState = getStateForTime(t);
        setCatState(targetState);

        animTickerId = requestAnimationFrame(tick);
    }

    /**
     * Hentikan audio dan kembalikan posisi ke awal (idle).
     */
    function stopAndReset() {
        audio.pause();
        audio.currentTime = 0;
        isFullMode = false;
        stopDeadline = 0;
        setCatState('idle');
        if (animTickerId) {
            cancelAnimationFrame(animTickerId);
            animTickerId = null;
        }
    }

    // Handler event saat lagu selesai 1 menit penuh
    audio.addEventListener('ended', () => {
        stopAndReset();
    });

    // Handler klik interaktif
    catContainer.addEventListener('click', (e) => {
        e.preventDefault();
        triggerBounce();

        // Mulai audio jika sedang terjeda
        if (audio.paused) {
            audio.play().catch(err => {
                console.warn('Autoplay audio dicegah oleh browser:', err);
            });
        }

        // Jika belum masuk Full Mode, set batas waktu berhenti 0.5 detik dari sekarang
        if (!isFullMode) {
            stopDeadline = audio.currentTime + 0.5;
        }

        // Pastikan loop ticker aktif
        if (!animTickerId) {
            animTickerId = requestAnimationFrame(tick);
        }
    });
});
