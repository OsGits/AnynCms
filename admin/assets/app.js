import { state } from './modules/state.js';
import { startClock } from './modules/utils.js';
import { getStatus, getTemplates, logout, changePassword, getSettings, setSiteName, selectTemplate } from './modules/api.js';
import { showSection, setPreview, renderTemplateSelect } from './modules/ui.js';

async function initFrontend() {
  const msg = document.getElementById('tpl-msg');
  const siteStatus = document.getElementById('site-name-status');
  const siteInput = document.getElementById('site-name-input');
  const siteKeywordsStatus = document.getElementById('site-keywords-status');
  const siteKeywords = document.getElementById('site-keywords-input');
  const siteDescriptionStatus = document.getElementById('site-description-status');
  const siteDescription = document.getElementById('site-description-input');
  const saveBtn = document.getElementById('save-template-btn');

  // 加载模板与当前选择
  let items = [];
  let selectedName = '';
  try {
    items = await getTemplates();
    try {
      const s = await getSettings();
      selectedName = s.selected_template || '';
    } catch (e) { /* 初次可能不存在 */ }

    if (!selectedName && items.length) selectedName = items[0].name;
    renderTemplateSelect(items, selectedName);
    if (selectedName) setPreview(selectedName);
  } catch (err) {
    msg.textContent = err.message || '加载模板失败';
  }

  // 加载站点设置（名称/关键字/描述）
  if (siteStatus) siteStatus.textContent = '';
  if (siteKeywordsStatus) siteKeywordsStatus.textContent = '';
  if (siteDescriptionStatus) siteDescriptionStatus.textContent = '';
  try {
    const s = await getSettings();
    if (siteInput) siteInput.value = s.site_name || '';
    if (siteKeywords) siteKeywords.value = s.site_keywords || '';
    if (siteDescription) siteDescription.value = s.site_description || '';
  } catch (err) {
    const m = err && err.message ? err.message : '网站设置加载失败';
    if (siteStatus) siteStatus.textContent = m;
  }

  // 统一保存（网站设置 + 模板选择）
  if (saveBtn) {
    saveBtn.addEventListener('click', async () => {
      if (msg) { msg.textContent = ''; msg.style.color = ''; }
      if (siteStatus) siteStatus.textContent = '';
      if (siteKeywordsStatus) siteKeywordsStatus.textContent = '';
      if (siteDescriptionStatus) siteDescriptionStatus.textContent = '';
      const originalText = saveBtn.textContent;

      const name = siteInput ? siteInput.value.trim() : '';
      const keywords = siteKeywords ? siteKeywords.value.trim() : '';
      const description = siteDescription ? siteDescription.value.trim() : '';
      const tpl = document.getElementById('template-select')?.value || selectedName;

      // 表单验证
      if (!name) { if (siteStatus) siteStatus.textContent = '请填写网站名称'; return; }
      if (name.length > 80) { if (siteStatus) siteStatus.textContent = '网站名称长度需在 1-80 字符'; return; }
      if (keywords && keywords.length > 200) { if (siteKeywordsStatus) siteKeywordsStatus.textContent = '关键字长度需不超过 200 字符'; return; }
      if (description && description.length > 300) { if (siteDescriptionStatus) siteDescriptionStatus.textContent = '网站描述长度需不超过 300 字符'; return; }
      if (!tpl) { if (msg) msg.textContent = '请选择模板'; return; }

      try {
        saveBtn.classList.add('loading');
        saveBtn.setAttribute('disabled', 'true');
        saveBtn.textContent = '保存中…';
        await setSiteName(name, keywords, description);
        await selectTemplate(tpl);
        if (msg) { msg.textContent = '已保存所有更改并应用模板'; msg.style.color = '#22c55e'; }
        setPreview(tpl);
      } catch (err) {
        const m = err && err.message ? err.message : '保存失败';
        if (msg) { msg.textContent = m; msg.style.color = ''; }
      } finally {
        saveBtn.classList.remove('loading');
        saveBtn.removeAttribute('disabled');
        saveBtn.textContent = originalText;
      }
    });
  }
}

document.addEventListener('DOMContentLoaded', async () => {
  // 提前绑定导航点击，避免后端状态异常导致事件未绑定
  const lis = document.querySelectorAll('.sidebar li');
  lis[0].addEventListener('click', () => showSection('home'));
  document.getElementById('nav-frontend').addEventListener('click', () => showSection('frontend'));
  document.getElementById('nav-resource').addEventListener('click', () => showSection('resource'));
  document.getElementById('nav-admin').addEventListener('click', () => showSection('admin'));

  showSection('home');

  let status = null;
  try {
    status = await getStatus();
  } catch (e) {
    // 在静态预览或后端未运行的情况下，允许仅前端UI导航
    status = null;
  }
  if (!status) return;

  const clock = document.getElementById('clock');
  startClock(clock);

  await initFrontend();

  document.getElementById('logout-btn').addEventListener('click', async () => {
    try { await logout(); } catch (err) { alert(err.message || '退出失败'); }
  });

  const pwdForm = document.getElementById('pwd-form');
  const pwdMsg = document.getElementById('pwd-msg');
  pwdForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    pwdMsg.textContent = '';
    const c = document.getElementById('current_password').value;
    const n = document.getElementById('new_password').value;
    if (!c || !n) { pwdMsg.textContent = '请填写当前密码和新密码'; return; }
    if (n.length < 6) { pwdMsg.textContent = '新密码长度至少 6 位'; return; }
    try {
      await changePassword(c, n);
      pwdMsg.textContent = '密码已更新';
      pwdMsg.style.color = '#22c55e';
      document.getElementById('current_password').value = '';
      document.getElementById('new_password').value = '';
    } catch (err) {
      pwdMsg.textContent = err.message || '更新失败';
      pwdMsg.style.color = '';
    }
  });
});