/**
 * Overdose - Client-Side Auto Redirect & Session Check
 */
document.addEventListener("DOMContentLoaded", async function () {
    // Memastikan variabel global BASE_URL tersedia dari PHP
    if (typeof BASE_URL === "undefined") {
        return;
    }

    // Eksekusi pengecekan sesi otomatis ke server
    try {
        const response = await fetch(BASE_URL + "app/api/auth/check_session.php", {
            method: "GET",
            headers: {
                "Accept": "application/json"
            }
        });

        if (response.ok) {
            const res = await response.json();

            // Jika sesi terdeteksi valid, lakukan auto-redirect
            if (res.status && res.data) {
                if (res.data.onboardingCompleted === false) {
                    window.location.href = BASE_URL + "settings?onboarding=true";
                } else {
                    window.location.href = BASE_URL + "dashboard";
                }
            }
        }
    } catch (err) {
        console.error("[Overdose] Gagal memeriksa status sesi otomatis:", err);
    }
});