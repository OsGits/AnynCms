export function formatTime(d) {
  const pad = n => String(n).padStart(2, '0');
  const y = d.getFullYear();
  const m = pad(d.getMonth() + 1);
  const day = pad(d.getDate());
  const hh = pad(d.getHours());
  const mm = pad(d.getMinutes());
  const ss = pad(d.getSeconds());
  return `${y}/${m}/${day} ${hh}:${mm}:${ss}`;
}

export function startClock(clockEl) {
  const tick = () => { clockEl.textContent = formatTime(new Date()); };
  tick();
  setInterval(tick, 1000);
}