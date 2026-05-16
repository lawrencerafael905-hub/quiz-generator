// assets/js/realtime.js — Pusher client-side real-time integration
// Pusher JS SDK is loaded separately: https://js.pusher.com/8.0/pusher.min.js

/**
 * Teacher dashboard: listen on multiple quiz channels for live submissions.
 */
function initDashboardRealtime(quizIds, appKey, cluster) {
    if (!appKey || !quizIds || quizIds.length === 0) return;

    const pusher = new Pusher(appKey, { cluster });

    quizIds.forEach(id => {
        const channel = pusher.subscribe('quiz-' + id);

        channel.bind('submission-received', data => {
            showBanner(
                `🎯 New submission from <strong>${escHtml(data.username)}</strong> — Score: ${data.score}%`
            );
        });
    });
}

/**
 * Results page: live leaderboard refresh when submission arrives.
 */
function initResultsRealtime(quizId, appKey, cluster) {
    if (!appKey) return;

    const pusher  = new Pusher(appKey, { cluster });
    const channel = pusher.subscribe('quiz-' + quizId);

    channel.bind('submission-received', data => {
        showBanner(`New submission from <strong>${escHtml(data.username)}</strong> — ${data.score}%. Reload to update leaderboard.`);
    });
}

/**
 * Student: subscribe after submission to receive leaderboard notification.
 */
function initStudentRealtime(quizId, appKey, cluster) {
    if (!appKey) return;

    const pusher  = new Pusher(appKey, { cluster });
    const channel = pusher.subscribe('quiz-' + quizId);

    channel.bind('submission-received', () => {
        // Student result page can optionally show rank updates
    });
}

// Helpers
function showBanner(html) {
    const el = document.getElementById('rt-notification');
    if (!el) return;
    el.innerHTML = html;
    el.style.display = 'block';
    el.classList.add('rt-flash');
    setTimeout(() => el.classList.remove('rt-flash'), 1000);
}

function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}
