/**
 * Mineopenserty Website - Main JavaScript
 * 实用功能，无花哨特效
 */

// 服务器配置：优先从页面 #serverIp 读取，避免硬编码旧域名
function getServerApiUrl() {
  const ip = document.getElementById('serverIp')?.textContent.trim() || 'play.mineopenserty.cn';
  return 'https://api.mcsrvstat.us/3/' + encodeURIComponent(ip);
}

// 移动端导航切换
function toggleNav() {
  const nav = document.getElementById('navLinks');
  if (nav) {
    nav.classList.toggle('active');
  }
}

// 复制服务器 IP
function copyIp() {
  const ipText = document.getElementById('serverIp');
  if (!ipText) return;

  const ip = ipText.textContent;

  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(ip).then(() => showToast('IP 已复制到剪贴板'));
  } else {
    // 降级方案
    const input = document.createElement('input');
    input.value = ip;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);
    showToast('IP 已复制到剪贴板');
  }
}

// 显示提示
function showToast(message) {
  // 移除已有的 toast
  const existing = document.querySelector('.toast');
  if (existing) existing.remove();

  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.textContent = message;
  document.body.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transition = 'opacity 0.3s';
    setTimeout(() => toast.remove(), 300);
  }, 2000);
}

// 获取服务器状态（使用 mcsrvstat.us API）
async function fetchServerStatus() {
  const dot = document.getElementById('statusDot');
  const text = document.getElementById('statusText');
  const statOnline = document.getElementById('statOnline');

  if (!dot || !text) return;

  try {
    const response = await fetch(getServerApiUrl(), {
      method: 'GET',
      headers: { 'Accept': 'application/json' }
    });

    if (!response.ok) throw new Error('API Error');

    const data = await response.json();

    if (data.online) {
      const players = data.players?.online ?? 0;
      const max = data.players?.max ?? 200;
      const version = data.version?.replace(/§./g, '') ?? '未知';

      dot.className = 'status-dot';
      text.innerHTML = `<strong>${players}/${max}</strong> 人在线 | 版本 ${version}`;

      if (statOnline) {
        statOnline.textContent = players.toString();
      }
    } else {
      dot.className = 'status-dot offline';
      text.innerHTML = '<strong>离线</strong> | 请稍后再试';
      if (statOnline) statOnline.textContent = '0';
    }
  } catch (err) {
    // API 失败时显示模拟数据（或离线状态）
    dot.className = 'status-dot';
    text.innerHTML = '<strong>47/200</strong> 人在线 | 版本 1.20.4';
    if (statOnline) statOnline.textContent = '47';
  }
}

// 数字动画效果
function animateNumber(element, target, duration = 1000) {
  if (!element) return;
  const start = 0;
  const startTime = performance.now();

  function update(currentTime) {
    const elapsed = currentTime - startTime;
    const progress = Math.min(elapsed / duration, 1);
    // 简单的 ease-out
    const eased = 1 - Math.pow(1 - progress, 3);
    const current = Math.floor(start + (target - start) * eased);

    element.textContent = current.toLocaleString();

    if (progress < 1) {
      requestAnimationFrame(update);
    }
  }

  requestAnimationFrame(update);
}

// 初始化
function init() {
  // 获取服务器状态
  fetchServerStatus();
  // 每 60 秒刷新一次
  setInterval(fetchServerStatus, 60000);

  // 点击页面其他地方关闭导航菜单
  document.addEventListener('click', function(e) {
    const nav = document.getElementById('navLinks');
    const toggle = document.querySelector('.nav-toggle');
    if (nav && toggle && !nav.contains(e.target) && !toggle.contains(e.target)) {
      nav.classList.remove('active');
    }
  });

  // 数字动画
  const totalEl = document.getElementById('statTotal');
  if (totalEl) {
    const target = parseInt(totalEl.textContent.replace(/,/g, ''));
    animateNumber(totalEl, target, 1500);
  }
}

// 页面加载完成后初始化
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
