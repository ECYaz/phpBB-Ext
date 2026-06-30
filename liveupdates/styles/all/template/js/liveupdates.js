/* Live Updates — adaptive poller. */
(function () {
    'use strict';

    var boot = document.getElementById('lu-bootstrap');
    if (!boot) { return; }

    var config;
    try { config = JSON.parse(boot.textContent || '{}'); } catch (e) { return; }
    if (!config || !config.enabled) { return; }

    var pollUrl = boot.getAttribute('data-poll-url');
    if (!pollUrl) { return; }
    var surfaces = config.surfaces || {};
    var baseInterval = Math.max(config.minInterval || 1, config.interval || 10) * 1000;
    var minInterval = (config.minInterval || 1) * 1000;
    var IDLE_LIMIT = 5 * 60 * 1000;

    var timer = null;
    var failures = 0;
    var running = true;
    var lastInteraction = Date.now();

    function ctx() {
        var params = new URLSearchParams();
        var topic = document.getElementById('lu-topic-anchor');
        if (topic && surfaces.topic) {
            params.set('topic_id', topic.getAttribute('data-topic-id'));
            params.set('forum_id', topic.getAttribute('data-forum-id'));
            params.set('last_post_id', String(highestPostId()));
        }
        var index = document.getElementById('lu-index-anchor');
        var forum = document.getElementById('lu-forum-anchor');
        if ((index || forum) && surfaces.index) {
            params.set('since', String(window.luIndexSince || Math.floor(Date.now() / 1000)));
            if (forum) { params.set('forum_id', forum.getAttribute('data-forum-id')); }
        }
        if (surfaces.stats && document.querySelector('.stat-block.statistics')) { params.set('stats', '1'); }
        if (surfaces.online && document.querySelector('.stat-block.online-list')) { params.set('online', '1'); }
        return params.toString();
    }

    function highestPostId() {
        var posts = document.querySelectorAll('[id^="p"]');
        var max = 0;
        posts.forEach(function (el) {
            var m = /^p(\d+)$/.exec(el.id);
            if (m) { max = Math.max(max, parseInt(m[1], 10)); }
        });
        return max;
    }

    function currentInterval() {
        // exponential backoff on failures, capped
        return Math.min(baseInterval * Math.pow(2, failures), 5 * 60 * 1000);
    }

    function schedule(delay) {
        clearTimeout(timer);
        timer = setTimeout(tick, Math.max(minInterval, delay));
    }

    function tick() {
        if (document.hidden || (Date.now() - lastInteraction) > IDLE_LIMIT) {
            running = false; return; // paused; focus/interaction will resume
        }
        var q = ctx(); fetch(pollUrl + (q ? '?' + q : ''), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) {
            if (r.status === 429) { failures = 0; return null; }
            if (!r.ok) { throw new Error('bad status'); }
            return r.json();
        }).then(function (data) {
            if (data) {
                failures = 0;
                if (data.interval) { baseInterval = Math.max(minInterval, data.interval * 1000); }
                if (data.deltas) { handleDeltas(data.deltas); }
            }
            schedule(baseInterval);
        }).catch(function () {
            failures = Math.min(failures + 1, 3);
            if (failures >= 3) { running = false; return; } // stop until focus
            schedule(currentInterval());
        });
    }

    function handleDeltas(deltas) {
        if (deltas.topic && window.luHandleTopic) { window.luHandleTopic(deltas.topic); }
        if (deltas.notify && window.luHandleNotify) { window.luHandleNotify(deltas.notify); }
        if (deltas.index && window.luHandleIndex) { window.luHandleIndex(deltas.index); }
        if (deltas.pm && window.luHandlePm) { window.luHandlePm(deltas.pm); }
        if (deltas.stats && window.luHandleStats) { window.luHandleStats(deltas.stats); }
        if (deltas.online && window.luHandleOnline) { window.luHandleOnline(deltas.online); }
    }

    function wake() {
        running = true;
        lastInteraction = Date.now();
        if (failures >= 3) { failures = 0; }
        schedule(0);
    }

    document.addEventListener('visibilitychange', function () { if (!document.hidden) { wake(); } });
    ['click', 'keydown', 'scroll', 'mousemove'].forEach(function (ev) {
        window.addEventListener(ev, function () { lastInteraction = Date.now(); if (!running) { wake(); } }, { passive: true });
    });
    window.addEventListener('focus', wake);

    /* ── Topic hybrid handler (Task 12) ─────────────────────────────────── */

    var luTopicSeen = highestPostId();
    var hydrating = false;

    window.luHandleTopic = function (topic) {
        if (!topic || !topic.count || topic.last_post_id <= luTopicSeen) { return; }
        var fullHeight = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
        var nearBottom = (window.innerHeight + window.scrollY) >= (fullHeight - 150);
        if (nearBottom) {
            hydrateNewPosts();
        } else {
            showTopicBanner(topic.count);
        }
    };

    function topicBanner() {
        var el = document.getElementById('lu-topic-banner');
        if (!el) {
            var anchor = document.getElementById('lu-topic-anchor');
            el = document.createElement('a');
            el.id = 'lu-topic-banner';
            el.className = 'lu-banner';
            el.href = '#';
            el.hidden = true;
            el.addEventListener('click', function (e) { e.preventDefault(); hydrateNewPosts(); });
            anchor.parentNode.insertBefore(el, anchor);
        }
        return el;
    }

    function showTopicBanner(count) {
        var el = topicBanner();
        var s = config.strings;
        var tmpl = (s && s.newReply) ? (count === 1 ? s.newReply : s.newReplies) : (count === 1 ? '1 new reply' : count + ' new replies');
        el.textContent = tmpl.replace('%d', count);
        el.hidden = false;
    }

    function hydrateNewPosts() {
        if (hydrating) { return; }
        hydrating = true;
        fetch(window.location.href, { credentials: 'same-origin' })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var fresh = doc.querySelectorAll('#page-body .post');
                var container = document.querySelector('#page-body');
                var anchor = document.getElementById('lu-topic-anchor');
                var added = 0;
                fresh.forEach(function (node) {
                    var m = /^p(\d+)$/.exec(node.id);
                    var pid = m ? parseInt(m[1], 10) : 0;
                    if (pid > luTopicSeen && !document.getElementById(node.id)) {
                        container.insertBefore(node, anchor);
                        node.classList.add('lu-row-new');
                        luTopicSeen = Math.max(luTopicSeen, pid);
                        added++;
                    }
                });
                if (added === 0 && anchor) {
                    var tid = anchor.getAttribute('data-topic-id');
                    if (tid) { window.location.assign('./viewtopic.php?t=' + tid + '&view=unread'); }
                }
                if (added > 0) {
                    var banner = document.getElementById('lu-topic-banner');
                    if (banner) { banner.hidden = true; }
                }
            })
            .catch(function () { /* leave banner visible to retry on next click */ })
            .finally(function () { hydrating = false; });
    }

    /* ── Notification badge handler (Task 13) ───────────────────────────── */

    window.luHandleNotify = function (notify) {
        if (!notify) { return; }
        var badge = document.querySelector('#notification_list_button .badge');
        if (!badge) { return; }
        badge.textContent = notify.unread;
        if (notify.unread > 0) {
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    };

    /* ── PM badge handler (P2-C) ─────────────────────────────────────────── */

    window.luHandlePm = function (pm) {
        if (!pm) { return; }
        var notif = document.getElementById('notification_list_button');
        var scope = (notif && notif.closest('ul')) ? notif.closest('ul') : document;
        var inbox = scope.querySelector('.fa-inbox');
        var link = inbox ? inbox.closest('a') : null;
        var badge = link ? link.querySelector('.badge') : null;
        if (!badge) { return; }
        badge.textContent = pm.unread;
        if (pm.unread > 0) {
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    };

    /* ── Stats handler (P2-C) ────────────────────────────────────────────── */

    window.luHandleStats = function (stats) {
        if (!stats) { return; }
        var strs = document.querySelectorAll('.stat-block.statistics strong');
        if (strs.length < 4) { return; }
        strs[0].textContent = stats.posts;
        strs[1].textContent = stats.topics;
        strs[2].textContent = stats.members;
        var newestEl = strs[3].querySelector('a') || strs[3];
        newestEl.textContent = stats.newest;
    };

    /* ── Online count handler (P2-C) ─────────────────────────────────────── */

    window.luHandleOnline = function (online) {
        if (!online) { return; }
        var strs = document.querySelectorAll('.stat-block.online-list strong');
        if (!strs.length) { return; }
        strs[0].textContent = online.online;
    };

    /* ── Index / forum banner handler (Task 13) ──────────────────────────── */

    window.luIndexSince = Math.floor(Date.now() / 1000);
    window.luHandleIndex = function (index) {
        if (!index) { return; }
        if (index.since) { window.luIndexSince = index.since; }
        if (!index.count) { return; }
        var anchor = document.getElementById('lu-index-anchor') || document.getElementById('lu-forum-anchor');
        if (!anchor) { return; }
        var el = document.getElementById('lu-index-banner');
        if (!el) {
            el = document.createElement('a');
            el.id = 'lu-index-banner';
            el.className = 'lu-banner';
            el.href = '#';
            el.addEventListener('click', function (e) { e.preventDefault(); window.location.reload(); });
            anchor.parentNode.insertBefore(el, anchor);
        }
        var s = config.strings;
        var tmpl = (s && s.newTopic) ? (index.count === 1 ? s.newTopic : s.newTopics) : (index.count === 1 ? '1 new topic' : index.count + ' new topics');
        el.textContent = tmpl.replace('%d', index.count);
        el.hidden = false;
    };

    schedule(baseInterval);
}());
