import { selectTemplate } from './api.js';

export function showSection(name) {
  // 动态读取各区块，避免因元素缺失导致报错
  const sections = {
    home: document.getElementById('home-section'),
    frontend: document.getElementById('frontend-section'),
    resource: document.getElementById('resource-section'),
    admin: document.getElementById('admin-section')
  };
  Object.values(sections).forEach(el => { if (el) el.classList.add('hidden'); });
  const target = sections[name] || sections.home;
  if (target) target.classList.remove('hidden');

  // 侧边栏高亮
  const lis = document.querySelectorAll('.sidebar li');
  lis.forEach(li => li.classList.remove('active'));
  if (name === 'frontend') {
    const el = document.getElementById('nav-frontend'); if (el) el.classList.add('active');
  } else if (name === 'resource') {
    const el = document.getElementById('nav-resource'); if (el) el.classList.add('active');
  } else if (name === 'admin') {
    const el = document.getElementById('nav-admin'); if (el) el.classList.add('active');
  } else if (lis[0]) {
    lis[0].classList.add('active');
  }
}

export function setPreview(name) {
  const frame = document.getElementById('preview-frame');
  // 通过后端渲染器预览，以便短代码生效
  if (frame) frame.src = `../index.php?tpl=${encodeURIComponent(name)}`;
}

export function renderTemplateSelect(items, selectedName) {
  const select = document.getElementById('template-select');
  if (!select) return;
  select.innerHTML = '';
  items.forEach(t => {
    const opt = document.createElement('option');
    opt.value = t.name;
    opt.textContent = t.name + (t.has_index ? '' : ' (缺少 index.html)');
    if (t.name === selectedName) opt.selected = true;
    select.appendChild(opt);
  });
  select.addEventListener('change', () => {
    const name = select.value;
    setPreview(name);
  });
  // 初始化时设置预览
  if (selectedName) setPreview(selectedName);
  // 统一保存按钮由 app.js 处理，这里仅负责模板选择与预览联动。
}