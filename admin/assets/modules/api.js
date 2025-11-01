import { state } from './state.js';

export async function getStatus() {
  const res = await fetch('./index.php?action=status', { credentials: 'same-origin' });
  const data = await res.json();
  if (!data.logged_in) {
    window.location.href = './login.html';
    return null;
  }
  state.csrf = data.csrf_token || '';
  state.user = data.user || null;
  return data;
}

export async function getTemplates() {
  const res = await fetch('./index.php?action=list', { credentials: 'same-origin' });
  const data = await res.json();
  if (data.error) throw new Error(data.error);
  return data.templates || [];
}

export async function selectTemplate(name) {
  const res = await fetch('./index.php?action=select', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({ template: name, csrf_token: state.csrf })
  });
  const data = await res.json();
  if (data.error) throw new Error(data.error);
  return data;
}

export async function logout() {
  const res = await fetch('./index.php?action=logout', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({ csrf_token: state.csrf })
  });
  const data = await res.json();
  if (data.error) throw new Error(data.error);
  window.location.href = './login.html';
}

export async function changePassword(current, next) {
  const res = await fetch('./index.php?action=admin_change_password', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({ current_password: current, new_password: next, csrf_token: state.csrf })
  });
  const data = await res.json();
  if (data.error) throw new Error(data.error);
  return data;
}

// 站点设置：读取与保存
export async function getSettings() {
  const res = await fetch('./index.php?action=settings_get', { credentials: 'same-origin' });
  const data = await res.json();
  if (data.error) throw new Error(data.error);
  state.csrf = data.csrf_token || state.csrf;
  return {
    site_name: data.site_name || '',
    site_keywords: data.site_keywords || '',
    site_description: data.site_description || '',
    selected_template: data.selected_template || ''
  };
}

export async function setSiteName(name, keywords = '', description = '') {
  const res = await fetch('./index.php?action=settings_set', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({ site_name: name, site_keywords: keywords, site_description: description, csrf_token: state.csrf })
  });
  const data = await res.json();
  if (data.error) throw new Error(data.error);
  return { site_name: data.site_name || name, site_keywords: data.site_keywords || keywords, site_description: data.site_description || description };
}
