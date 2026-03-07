(() => {
  if (window.__TASKORA_PROJECTS_INIT__) return;
  window.__TASKORA_PROJECTS_INIT__ = true;

  const qs = (s) => document.querySelector(s);
  const qsa = (s) => Array.from(document.querySelectorAll(s));

  function encodeForm(data) {
    return Object.entries(data)
      .map(([k, v]) => encodeURIComponent(k) + '=' + encodeURIComponent(v ?? ''))
      .join('&');
  }

  function escapeHtml(str) {
    return String(str || '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
  }

  // Lightweight renderer to keep card preview in sync after project edit.
  function renderDescriptionToHtml(text) {
    const normalized = String(text || '').replace(/\r\n?/g, '\n');
    const escaped = escapeHtml(normalized).replace(/\*\*(.+?)\*\*/gs, '<strong>$1</strong>');
    const lines = escaped.split('\n');
    let inUl = false;
    let inOl = false;
    const out = [];

    const closeLists = () => {
      if (inUl) { out.push('</ul>'); inUl = false; }
      if (inOl) { out.push('</ol>'); inOl = false; }
    };

    lines.forEach((line) => {
      const trim = line.trimStart();
      const ul = trim.match(/^-\s+(.+)$/);
      if (ul) {
        if (inOl) { out.push('</ol>'); inOl = false; }
        if (!inUl) { out.push('<ul>'); inUl = true; }
        out.push(`<li>${ul[1]}</li>`);
        return;
      }

      const ol = trim.match(/^\d+\.\s+(.+)$/);
      if (ol) {
        if (inUl) { out.push('</ul>'); inUl = false; }
        if (!inOl) { out.push('<ol>'); inOl = true; }
        out.push(`<li>${ol[1]}</li>`);
        return;
      }

      closeLists();
      if (trim === '') out.push('<br>');
      else out.push(`${line}<br>`);
    });

    closeLists();
    return out.join('\n');
  }

  async function refreshProgressBars() {
    const cards = qsa('.project-card[data-project-id]');
    if (cards.length === 0) return;

    try {
      const res = await fetch('api/get_projects.php', { credentials: 'same-origin' });
      if (!res.ok) return;
      const rows = await res.json();
      const map = new Map(rows.map(r => [String(r.id), r]));

      cards.forEach(card => {
        const id = card.getAttribute('data-project-id');
        const row = map.get(String(id));
        if (!row) return;

        const pct = Number(row.progress_percent ?? 0);
        const done = Number(row.done_count ?? 0);
        const total = Number(row.total_count ?? 0);

        const bar = card.querySelector('.progress-bar');
        const pctEl = card.querySelector('.pct');
        const countsEl = card.querySelector('.counts');

        if (bar) bar.style.width = `${Math.max(0, Math.min(100, pct))}%`;
        if (pctEl) pctEl.textContent = `${pct}%`;
        if (countsEl) countsEl.textContent = `${done}/${total}`;
      });
    } catch (_) {
      // silent
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    // Wire modal
    const openBtn = qs('#openCreateProject');
    const modalEl = qs('#createProjectModal');
    const createBtn = qs('#createProjectBtn');
    const titleEl = qs('#projectTitle');
    const descEl = qs('#projectDesc');

    let modal = null;
    if (modalEl && window.bootstrap && window.bootstrap.Modal) {
      modal = new window.bootstrap.Modal(modalEl);
    }

    if (openBtn) {
      openBtn.addEventListener('click', () => {
        if (modal) {
          modal.show();
          setTimeout(() => titleEl?.focus(), 120);
        } else {
          // Fallback if bootstrap didn't load
          modalEl?.classList.add('show');
          modalEl?.setAttribute('style', 'display:block; background: rgba(0,0,0,.35)');
        }
      });
    }

    if (createBtn) {
      createBtn.addEventListener('click', async () => {
        const title = (titleEl?.value || '').trim();
        const description = (descEl?.value || '');

        if (!title) {
          alert('Podaj tytuł projektu.');
          titleEl?.focus();
          return;
        }

        createBtn.disabled = true;
        createBtn.textContent = 'Tworzenie...';

        try {
          const res = await fetch('api/create_project.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: encodeForm({ title, description }),
            credentials: 'same-origin'
          });

          const data = await res.json().catch(() => ({}));

          if (res.ok && data.success && data.id) {
            // go straight into project board
            window.location.href = `index.php?project_id=${encodeURIComponent(data.id)}`;
            return;
          }

          alert(data.error || 'Nie udało się utworzyć projektu.');
        } catch (e) {
          alert('Błąd połączenia. Spróbuj ponownie.');
        } finally {
          createBtn.disabled = false;
          createBtn.textContent = 'Utwórz';
        }
      });
    }


    // Delete project
    const editModalEl = qs('#editProjectModal');
    const editProjectIdEl = qs('#editProjectId');
    const editProjectTitleEl = qs('#editProjectTitle');
    const editProjectDescEl = qs('#editProjectDesc');
    const saveProjectBtn = qs('#saveProjectBtn');
    let editModal = null;

    if (editModalEl && window.bootstrap && window.bootstrap.Modal) {
      editModal = new window.bootstrap.Modal(editModalEl);
    }

    qsa('.project-edit-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();

        const pid = btn.getAttribute('data-project-id') || '';
        const pTitle = btn.getAttribute('data-project-title') || '';
        const pDesc = btn.getAttribute('data-project-description') || '';

        if (editProjectIdEl) editProjectIdEl.value = pid;
        if (editProjectTitleEl) editProjectTitleEl.value = pTitle;
        if (editProjectDescEl) editProjectDescEl.value = pDesc;

        if (editModal) {
          editModal.show();
          setTimeout(() => editProjectTitleEl?.focus(), 120);
        } else {
          editModalEl?.classList.add('show');
          editModalEl?.setAttribute('style', 'display:block; background: rgba(0,0,0,.35)');
        }
      });
    });

    if (saveProjectBtn) {
      saveProjectBtn.addEventListener('click', async () => {
        const project_id = (editProjectIdEl?.value || '').trim();
        const title = (editProjectTitleEl?.value || '').trim();
        const description = (editProjectDescEl?.value || '');

        if (!project_id) return;
        if (!title) {
          alert('Podaj tytuł projektu.');
          editProjectTitleEl?.focus();
          return;
        }

        saveProjectBtn.disabled = true;
        const oldTxt = saveProjectBtn.textContent;
        saveProjectBtn.textContent = 'Zapisywanie...';

        try {
          const res = await fetch('api/update_project.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: encodeForm({ project_id, title, description }),
            credentials: 'same-origin'
          });

          const data = await res.json().catch(() => ({}));
          if (!(res.ok && data.success)) {
            alert(data.error || 'Nie udało się zapisać projektu.');
            return;
          }

          const card = document.querySelector(`.project-card--wrap[data-project-id="${CSS.escape(String(project_id))}"]`);
          if (card) {
            const titleNodes = card.querySelectorAll('.project-card__title');
            titleNodes.forEach((node) => { node.textContent = title; });

            const descNode = card.querySelector('.project-card__desc');
            if (descNode) descNode.innerHTML = renderDescriptionToHtml(description);

            const delBtn = card.querySelector('.project-delete-btn');
            if (delBtn) delBtn.setAttribute('data-project-title', title);

            const editBtn = card.querySelector('.project-edit-btn');
            if (editBtn) {
              editBtn.setAttribute('data-project-title', title);
              editBtn.setAttribute('data-project-description', description);
            }
          }

          if (editModal) editModal.hide();
        } catch (_) {
          alert('Błąd połączenia. Spróbuj ponownie.');
        } finally {
          saveProjectBtn.disabled = false;
          saveProjectBtn.textContent = oldTxt;
        }
      });
    }

    const delModalEl = qs('#deleteProjectModal');
    const delNameEl = qs('#deleteProjectName');
    const delConfirmBtn = qs('#confirmDeleteProjectBtn');
    let delModal = null;
    let projectToDelete = null;

    if (delModalEl && window.bootstrap && window.bootstrap.Modal) {
      delModal = new window.bootstrap.Modal(delModalEl);
    }

    qsa('.project-delete-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        projectToDelete = btn.getAttribute('data-project-id');
        const title = btn.getAttribute('data-project-title') || '';
        if (delNameEl) delNameEl.textContent = title || `ID ${projectToDelete}`;
        if (delModal) delModal.show();
        else {
          // fallback
          delModalEl?.classList.add('show');
          delModalEl?.setAttribute('style', 'display:block; background: rgba(0,0,0,.35)');
        }
      });
    });

    if (delConfirmBtn) {
      delConfirmBtn.addEventListener('click', async () => {
        if (!projectToDelete) return;

        delConfirmBtn.disabled = true;
        const oldTxt = delConfirmBtn.textContent;
        delConfirmBtn.textContent = 'Usuwanie...';

        try {
          const res = await fetch('api/delete_project.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: encodeForm({ project_id: projectToDelete }),
            credentials: 'same-origin'
          });
          const data = await res.json().catch(() => ({}));

          if (res.ok && data.success) {
            // remove card from DOM
            const card = document.querySelector(`.project-card--wrap[data-project-id="${CSS.escape(String(projectToDelete))}"]`);
            const col = card?.closest('.col-12');
            if (col) col.remove(); else card?.remove();

            // hide modal
            if (delModal) delModal.hide();

            // empty-state message if none left
            if (document.querySelectorAll('.project-card--wrap').length === 0) {
              const grid = qs('#projectsGrid');
              if (grid) {
                grid.innerHTML = '<div class="col-12"><div class="alert alert-info">Nie masz jeszcze projektów. Kliknij <b>+ Dodaj projekt</b> w prawym górnym rogu.</div></div>';
              }
            }
            return;
          }

          alert(data.error || 'Nie udało się usunąć projektu.');
        } catch (e) {
          alert('Błąd połączenia. Spróbuj ponownie.');
        } finally {
          delConfirmBtn.disabled = false;
          delConfirmBtn.textContent = oldTxt;
          projectToDelete = null;
        }
      });
    }

    refreshProgressBars();
  });
})();
