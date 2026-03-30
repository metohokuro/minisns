// assets/script.js - フロントエンドJS

/* ======================================
   フィンガープリント管理
====================================== */
const FP = {
  key: 'minisns_fp',

  /** フィンガープリント取得（FingerprintJSまたはフォールバック）*/
  async get() {
    // キャッシュ確認
    const cached = localStorage.getItem(this.key);
    if (cached) return cached;

    try {
      const fp = await FingerprintJS.load();
      const result = await fp.get();
      const visitorId = result.visitorId;
      localStorage.setItem(this.key, visitorId);
      return visitorId;
    } catch (e) {
      // フォールバック: シンプルなブラウザ特性ハッシュ
      const raw = [
        navigator.userAgent,
        navigator.language,
        screen.width + 'x' + screen.height,
        Intl.DateTimeFormat().resolvedOptions().timeZone,
        navigator.hardwareConcurrency || 0,
        navigator.platform || '',
      ].join('|');
      const hash = await this._hash(raw);
      localStorage.setItem(this.key, hash);
      return hash;
    }
  },

  /** SHA-256ハッシュ（Web Crypto API）*/
  async _hash(str) {
    const buf  = new TextEncoder().encode(str);
    const hash = await crypto.subtle.digest('SHA-256', buf);
    return Array.from(new Uint8Array(hash))
      .map(b => b.toString(16).padStart(2, '0'))
      .join('');
  },

  /** 全フォームに自動注入 */
  async injectAll() {
    const fp = await this.get();
    document.querySelectorAll('input[name="fingerprint"]').forEach(el => {
      el.value = fp;
    });
    return fp;
  },
};

/* ======================================
   自動ログイン（フィンガープリント）
====================================== */
const AutoLogin = {
  async check() {
    // ログイン済みなら不要
    if (document.body.dataset.loggedIn === '1') return;

    const banner = document.getElementById('autologin-banner');
    if (!banner) return;

    const fp = await FP.get();

    try {
      const res = await fetch('api.php?action=check_fingerprint', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ fingerprint: fp }),
      });
      const data = await res.json();

      if (data.user_id) {
        const name = data.display_name || data.user_id;
        banner.innerHTML = `
          <div class="autologin-text">
            <span class="autologin-name">${escapeHtml(name)}</span> さんとしてログインしますか？
          </div>
          <div style="display:flex;gap:8px;flex-shrink:0">
            <button class="btn btn-primary btn-sm" onclick="AutoLogin.login('${escapeHtml(data.user_id)}', '${escapeHtml(fp)}')">
              ログイン
            </button>
            <button class="btn btn-outline btn-sm" onclick="this.closest('.autologin-banner').remove()">
              別のアカウント
            </button>
          </div>
        `;
        banner.style.display = 'flex';
      }
    } catch (e) {
      console.warn('フィンガープリントチェック失敗', e);
    }
  },

  async login(userId, fp) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'api.php?action=fingerprint_login';
    form.innerHTML = `
      <input name="user_id" value="${escapeHtml(userId)}">
      <input name="fingerprint" value="${escapeHtml(fp)}">
      <input name="csrf_token" value="${document.querySelector('[name=csrf_token_meta]')?.content || ''}">
    `;
    document.body.appendChild(form);
    form.submit();
  },
};

/* ======================================
   投稿フォーム
====================================== */
const Compose = {
  init() {
    const textarea = document.getElementById('compose-textarea');
    const counter  = document.getElementById('char-count');
    const btn      = document.getElementById('compose-submit');
    if (!textarea) return;

    textarea.addEventListener('input', () => {
      const len = textarea.value.length;
      const max = 280;
      if (counter) {
        counter.textContent = `${len} / ${max}`;
        counter.className = 'char-count' + (len > max * .9 ? ' warn' : '') + (len > max ? ' error' : '');
      }
      if (btn) btn.disabled = len === 0 || len > max;
    });

    // Ctrl+Enter で投稿
    textarea.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        document.getElementById('compose-form')?.submit();
      }
    });
  },
};

/* ======================================
   いいね（非同期）
====================================== */
const Like = {
  async toggle(postId, btn) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrfToken) return;

    btn.disabled = true;

    try {
      const res = await fetch('like.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `post_id=${postId}&csrf_token=${encodeURIComponent(csrfToken)}`,
      });
      const data = await res.json();

      if (data.success) {
        const countEl = btn.querySelector('.like-count');
        if (countEl) countEl.textContent = data.count;

        btn.classList.toggle('liked', data.liked);
        // ハートアニメーション
        btn.classList.add('pop');
        setTimeout(() => btn.classList.remove('pop'), 300);
      }
    } catch (e) {
      console.error('いいね失敗', e);
    } finally {
      btn.disabled = false;
    }
  },
};

/* ======================================
   コメントトグル
====================================== */
const Comments = {
  toggle(postId) {
    const section = document.getElementById(`comments-${postId}`);
    if (!section) return;
    section.classList.toggle('open');

    if (section.classList.contains('open') && section.dataset.loaded !== '1') {
      this.load(postId, section);
    }
  },

  async load(postId, section) {
    section.innerHTML = '<div style="padding:12px;color:var(--text-muted)"><span class="spinner"></span> 読み込み中...</div>';

    try {
      const res  = await fetch(`comment.php?action=get&post_id=${postId}`);
      const data = await res.json();

      let html = data.comments.map(c => `
        <div class="comment-item">
          <div class="avatar avatar-sm" style="background:${escapeHtml(c.avatar_color)}">
            ${escapeHtml(c.initial)}
          </div>
          <div class="comment-body">
            <div class="comment-user">${escapeHtml(c.display_name || c.user_id)}</div>
            <div class="comment-text">${escapeHtml(c.content)}</div>
          </div>
        </div>
      `).join('');

      if (document.body.dataset.loggedIn === '1') {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        html += `
          <div class="comment-form">
            <input class="comment-input" placeholder="コメントを書く..." id="comment-input-${postId}" maxlength="200">
            <button class="btn btn-primary btn-sm" onclick="Comments.submit(${postId})">送信</button>
          </div>
        `;
      }

      section.innerHTML = html || '<div style="padding:12px;color:var(--text-muted)">コメントはまだありません</div>';
      if (!html.includes('comment-input')) {
        // 未ログイン時はフォームなし
      } else if (!html.trim()) {
        section.innerHTML = '<div style="padding:12px;color:var(--text-muted)">コメントはまだありません</div>' + section.innerHTML;
      }

      section.dataset.loaded = '1';
    } catch (e) {
      section.innerHTML = '<div style="padding:12px;color:var(--danger)">読み込みに失敗しました</div>';
    }
  },

  async submit(postId) {
    const input = document.getElementById(`comment-input-${postId}`);
    if (!input || !input.value.trim()) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const btn = input.nextElementSibling;
    btn.disabled = true;

    try {
      const res = await fetch('comment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=post&post_id=${postId}&content=${encodeURIComponent(input.value)}&csrf_token=${encodeURIComponent(csrfToken)}`,
      });
      const data = await res.json();

      if (data.success) {
        // コメント数更新
        const countEl = document.querySelector(`[data-comment-count="${postId}"]`);
        if (countEl) countEl.textContent = parseInt(countEl.textContent || 0) + 1;

        input.value = '';
        // セクション再読み込み
        const section = document.getElementById(`comments-${postId}`);
        if (section) {
          section.dataset.loaded = '0';
          await this.load(postId, section);
        }
      } else {
        alert(data.error || 'エラーが発生しました');
      }
    } catch (e) {
      alert('エラーが発生しました');
    } finally {
      btn.disabled = false;
    }
  },
};

/* ======================================
   フォロー（非同期）
====================================== */
const Follow = {
  async toggle(targetId, btn) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrfToken) return;

    btn.disabled = true;

    try {
      const res = await fetch('follow.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `target_id=${encodeURIComponent(targetId)}&csrf_token=${encodeURIComponent(csrfToken)}`,
      });
      const data = await res.json();

      if (data.success) {
        btn.textContent = data.following ? 'フォロー中' : 'フォロー';
        btn.classList.toggle('btn-outline', data.following);
        btn.classList.toggle('btn-primary', !data.following);
      }
    } catch (e) {
      console.error('フォロー失敗', e);
    } finally {
      btn.disabled = false;
    }
  },
};

/* ======================================
   モーダル
====================================== */
const Modal = {
  open(id) {
    document.getElementById(id)?.classList.add('open');
  },
  close(id) {
    document.getElementById(id)?.classList.remove('open');
  },
};

/* ======================================
   ユーティリティ
====================================== */
function escapeHtml(str) {
  const d = document.createElement('div');
  d.appendChild(document.createTextNode(String(str)));
  return d.innerHTML;
}

/* ======================================
   初期化
====================================== */
document.addEventListener('DOMContentLoaded', async () => {
  // フォームへフィンガープリント注入
  await FP.injectAll();

  // 自動ログイン確認
  await AutoLogin.check();

  // 投稿フォーム初期化
  Compose.init();

  // モーダルオーバーレイクリックで閉じる
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) overlay.classList.remove('open');
    });
  });
});

/* ======================================
   v2 追加機能
====================================== */

// ===== リプライ =====
const Reply = {
  open(postId, displayName) {
    document.getElementById('reply-to-id').value  = postId;
    document.getElementById('reply-modal-name').textContent = displayName;
    document.getElementById('reply-textarea').value = '';
    document.getElementById('reply-char-count').textContent = '0 / 280';
    Modal.open('reply-modal');
    setTimeout(() => document.getElementById('reply-textarea').focus(), 150);
  },
};

// リプライTextareaの文字数カウント
document.addEventListener('DOMContentLoaded', () => {
  const ta = document.getElementById('reply-textarea');
  const ct = document.getElementById('reply-char-count');
  if (ta && ct) {
    ta.addEventListener('input', () => {
      const len = ta.value.length;
      ct.textContent = `${len} / 280`;
      ct.className = 'char-count' + (len > 252 ? ' warn' : '') + (len > 280 ? ' error' : '');
    });
  }
});

// ===== ハッシュタグ補完（#を打つと候補表示）=====
const HashtagComplete = {
  init() {
    document.querySelectorAll('textarea').forEach(ta => {
      ta.addEventListener('input', (e) => this.onInput(e));
      ta.addEventListener('keydown', (e) => this.onKeyDown(e));
    });
  },

  currentTa: null,
  popup: null,
  candidates: [],
  selected: -1,

  onInput(e) {
    const ta  = e.target;
    const val = ta.value;
    const pos = ta.selectionStart;
    // カーソル位置の前の #word を抽出
    const before = val.slice(0, pos);
    const m = before.match(/#([\p{L}\p{N}_ぁ-ん一-龠ァ-ン]*)$/u);
    if (!m) { this.hidePopup(); return; }

    const query = m[1];
    this.currentTa = ta;
    this.fetchCandidates(query);
  },

  async fetchCandidates(query) {
    if (query.length === 0) { this.hidePopup(); return; }
    try {
      const res  = await fetch(`api.php?action=hashtag_suggest&q=${encodeURIComponent(query)}`);
      const data = await res.json();
      if (data.tags && data.tags.length > 0) {
        this.showPopup(data.tags);
      } else {
        this.hidePopup();
      }
    } catch { this.hidePopup(); }
  },

  showPopup(tags) {
    this.candidates = tags;
    this.selected   = -1;

    if (!this.popup) {
      this.popup = document.createElement('div');
      this.popup.className = 'hashtag-popup';
      document.body.appendChild(this.popup);
    }

    this.popup.innerHTML = tags.map((t, i) =>
      `<div class="hashtag-popup-item" data-idx="${i}" onclick="HashtagComplete.pick(${i})">#${escapeHtml(t)}</div>`
    ).join('');

    // 位置決め（textareaの下）
    const rect = this.currentTa.getBoundingClientRect();
    this.popup.style.top  = (rect.bottom + window.scrollY + 4) + 'px';
    this.popup.style.left = rect.left + 'px';
    this.popup.style.display = 'block';
  },

  hidePopup() {
    if (this.popup) this.popup.style.display = 'none';
    this.candidates = [];
  },

  pick(idx) {
    const tag = this.candidates[idx];
    if (!tag || !this.currentTa) return;
    const ta  = this.currentTa;
    const val = ta.value;
    const pos = ta.selectionStart;
    const before = val.slice(0, pos);
    const after  = val.slice(pos);
    const newBefore = before.replace(/#[\p{L}\p{N}_ぁ-ん一-龠ァ-ン]*$/u, '#' + tag);
    ta.value = newBefore + after;
    ta.selectionStart = ta.selectionEnd = newBefore.length;
    ta.dispatchEvent(new Event('input'));
    this.hidePopup();
  },

  onKeyDown(e) {
    if (!this.popup || this.popup.style.display === 'none') return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      this.selected = Math.min(this.selected + 1, this.candidates.length - 1);
      this.highlight();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      this.selected = Math.max(this.selected - 1, 0);
      this.highlight();
    } else if (e.key === 'Enter' && this.selected >= 0) {
      e.preventDefault();
      this.pick(this.selected);
    } else if (e.key === 'Escape') {
      this.hidePopup();
    }
  },

  highlight() {
    this.popup.querySelectorAll('.hashtag-popup-item').forEach((el, i) => {
      el.classList.toggle('active', i === this.selected);
    });
  },
};

document.addEventListener('DOMContentLoaded', () => {
  HashtagComplete.init();
});

/* ======================================
   v3: 投稿削除
====================================== */
async function deletePost(postId, btn) {
  if (!confirm('この投稿を削除しますか？')) return;

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
  btn.disabled = true;

  try {
    const res = await fetch('delete_post.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `post_id=${postId}&csrf_token=${encodeURIComponent(csrfToken)}`,
    });
    const data = await res.json();

    if (data.success) {
      const card = btn.closest('.post-card');
      card.style.transition = 'opacity .25s, transform .25s';
      card.style.opacity = '0';
      card.style.transform = 'translateX(12px)';
      setTimeout(() => card.remove(), 260);
    } else {
      alert(data.error || 'エラーが発生しました');
      btn.disabled = false;
    }
  } catch (e) {
    alert('エラーが発生しました');
    btn.disabled = false;
  }
}
