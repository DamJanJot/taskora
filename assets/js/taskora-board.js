// Taskora v3 - projects + board + better editor (textarea with mini markdown toolbar)
(function () {
  const projectId = window.TASKORA_PROJECT_ID || 0;

  function mdWrap(textarea, before, after) {
    const el = textarea;
    const start = el.selectionStart || 0;
    const end = el.selectionEnd || 0;
    const value = el.value;
    const selected = value.substring(start, end);
    const newValue = value.substring(0, start) + before + selected + after + value.substring(end);
    el.value = newValue;
    const cursor = start + before.length + selected.length + after.length;
    el.focus();
    el.setSelectionRange(cursor, cursor);
  }

  function mdList(textarea, prefix) {
    const el = textarea;
    const start = el.selectionStart || 0;
    const end = el.selectionEnd || 0;
    const value = el.value;
    const selected = value.substring(start, end) || "punkt";
    const lines = selected.split(/\r?\n/).map(l => (l.trim() ? prefix + l : l));
    const replaced = lines.join("\n");
    const newValue = value.substring(0, start) + replaced + value.substring(end);
    el.value = newValue;
    const cursor = start + replaced.length;
    el.focus();
    el.setSelectionRange(cursor, cursor);
  }

  function bindMiniToolbar(root = document) {
    root.querySelectorAll(".mini-btn").forEach(btn => {
      btn.addEventListener("click", () => {
        const mode = btn.dataset.md;
        const targetId = btn.dataset.target;
        const textarea = targetId ? document.getElementById(targetId) : document.getElementById("taskDesc");
        if (!textarea) return;

        if (mode === "bold") return mdWrap(textarea, "**", "**");
        if (mode === "ul") return mdList(textarea, "- ");
        if (mode === "ol") return mdList(textarea, "1. ");
      });
    });
  }

  function createTaskElement(task) {
    const div = document.createElement("div");
    div.className = "task";
    div.dataset.id = task.id;
    div.dataset.status = task.status;

    div.innerHTML = `
      <h3>${escapeHtml(task.title || "")}</h3>
      <div class="task-desc">${task.description_html || ""}</div>
      <div class="task-actions">
        <button class="edit-task" title="Edytuj">✏️</button>
        <button class="delete-task" title="Usuń">🗑️</button>
      </div>
    `;

    // dblclick anywhere to edit
    div.addEventListener("dblclick", () => openEditModal(task.id));

    return div;
  }

  function escapeHtml(str) {
    return (str || "").replace(/[&<>"']/g, m => ({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;" }[m]));
  }

  // ------- PROJECTS VIEW -------
  async function hydrateProjectsProgress() {
    const grid = document.getElementById("projectsGrid");
    if (!grid) return;

    try {
      const res = await fetch("api/get_projects.php");
      const projects = await res.json();

      // Map by id
      const map = new Map(projects.map(p => [String(p.id), p]));
      grid.querySelectorAll("a.project-card").forEach(card => {
        const href = card.getAttribute("href") || "";
        const m = href.match(/project_id=(\d+)/);
        if (!m) return;
        const id = m[1];
        const p = map.get(id);
        if (!p) return;

        const pct = Number(p.progress_percent || 0);
        const total = Number(p.total_count || 0);
        const done = Number(p.done_count || 0);

        const bar = card.querySelector(".progress-bar");
        const pctEl = card.querySelector(".pct");
        const countsEl = card.querySelector(".counts");

        if (bar) bar.style.width = pct + "%";
        if (pctEl) pctEl.textContent = pct + "%";
        if (countsEl) countsEl.textContent = `${done}/${total}`;
      });
    } catch (e) {
      // silent
      console.warn("get_projects failed", e);
    }
  }

  function initCreateProject() {
    const btn = document.getElementById("openCreateProject");
    if (!btn) return;

    const modalEl = document.getElementById("createProjectModal");
    const modal = modalEl ? new bootstrap.Modal(modalEl) : null;

    btn.addEventListener("click", () => modal && modal.show());

    const createBtn = document.getElementById("createProjectBtn");
    if (createBtn) {
      createBtn.addEventListener("click", async () => {
        const title = (document.getElementById("projectTitle")?.value || "").trim();
        const description = document.getElementById("projectDesc")?.value || "";

        if (!title) return alert("Podaj tytuł projektu.");

        const body = new URLSearchParams();
        body.set("title", title);
        body.set("description", description);

        const res = await fetch("api/create_project.php", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body });
        const data = await res.json();
        if (data && data.success) {
          window.location.reload();
        } else {
          alert(data?.error || "Błąd tworzenia projektu.");
        }
      });
    }
  }

  // ------- BOARD VIEW -------
  const boardStatuses = ["ready", "progress", "review", "done"];
  let tasksCache = new Map(); // id -> task
  const sortableMap = new Map();

  function updateBoardCountsFromDom() {
    const statMap = {
      ready: document.getElementById("cnt-ready"),
      progress: document.getElementById("cnt-progress"),
      review: document.getElementById("cnt-review"),
      done: document.getElementById("cnt-done"),
    };

    boardStatuses.forEach((status) => {
      const list = document.getElementById(status);
      const count = list ? list.querySelectorAll(".task").length : 0;
      if (statMap[status]) statMap[status].textContent = String(count);
    });
  }

  async function persistOrderedColumn(status) {
    const list = document.getElementById(status);
    if (!list) return true;

    const orderedIds = Array.from(list.querySelectorAll(".task"))
      .map((el) => Number(el.dataset.id || 0))
      .filter((id) => Number.isInteger(id) && id > 0);

    if (orderedIds.length === 0) return true;

    const body = new URLSearchParams();
    body.set("action", "reorder");
    body.set("status", status);
    body.set("project_id", String(projectId));
    body.set("ordered_ids", JSON.stringify(orderedIds));

    const res = await fetch("api/update_task.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body
    });

    const data = await res.json().catch(() => ({}));
    return Boolean(data && data.success);
  }

  async function persistMove(evt) {
    const toStatus = evt.to?.id;
    const fromStatus = evt.from?.id;
    if (!toStatus) return;

    const okTo = await persistOrderedColumn(toStatus);
    if (!okTo) throw new Error("persist to failed");

    if (fromStatus && fromStatus !== toStatus) {
      const okFrom = await persistOrderedColumn(fromStatus);
      if (!okFrom) throw new Error("persist from failed");
    }
  }

  async function loadBoard() {
    const statEls = {
      ready: document.getElementById("cnt-ready"),
      progress: document.getElementById("cnt-progress"),
      review: document.getElementById("cnt-review"),
      done: document.getElementById("cnt-done"),
    };

    // clear
    boardStatuses.forEach(s => {
      const col = document.getElementById(s);
      if (col) col.innerHTML = "";
    });

    const res = await fetch("api/get_tasks.php?project_id=" + encodeURIComponent(projectId));
    const tasks = await res.json();

    tasksCache = new Map(tasks.map(t => [String(t.id), t]));

    const counts = { ready: 0, progress: 0, review: 0, done: 0 };
    tasks.forEach(task => {
      const st = task.status || "ready";
      if (counts[st] !== undefined) counts[st]++;

      const col = document.getElementById(st);
      if (col) col.appendChild(createTaskElement(task));
    });

    Object.keys(counts).forEach(k => {
      if (statEls[k]) statEls[k].textContent = String(counts[k]);
    });

    // drag & drop
    boardStatuses.forEach(status => {
      const el = document.getElementById(status);
      if (!el) return;

      const existing = sortableMap.get(status);
      if (existing) {
        existing.destroy();
        sortableMap.delete(status);
      }

      const instance = new Sortable(el, {
        group: "shared",
        animation: 150,
        onEnd: async function (evt) {
          try {
            await persistMove(evt);
            updateBoardCountsFromDom();
          } catch (_) {
            alert("Błąd przy zmianie statusu lub kolejności.");
            await loadBoard();
          }
        }
      });

      sortableMap.set(status, instance);
    });
  }

  function initAddTask() {
    const toggle = document.getElementById("toggleFormBtn");
    const form = document.getElementById("addTaskForm");
    if (toggle && form) {
      toggle.addEventListener("click", () => form.classList.toggle("hidden"));
    }

    const addBtn = document.getElementById("addTaskBtn");
    if (!addBtn) return;

    addBtn.addEventListener("click", async () => {
      const title = (document.getElementById("taskTitle")?.value || "").trim();
      const description = document.getElementById("taskDesc")?.value || "";

      if (!title) return alert("Podaj tytuł zadania.");

      const body = new URLSearchParams();
      body.set("title", title);
      body.set("description", description);
      body.set("project_id", String(projectId));
      const stSel = document.getElementById("taskStatus");
      const chosenStatus = stSel ? (stSel.value || "ready") : "ready";
      body.set("status", chosenStatus);

      const res = await fetch("api/create_task.php", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body });
      const data = await res.json();

      if (data?.success) {
        document.getElementById("taskTitle").value = "";
        document.getElementById("taskDesc").value = "";
        const stSel2 = document.getElementById("taskStatus");
        if (stSel2) stSel2.value = "ready";
        form?.classList.add("hidden");
        await loadBoard();
      } else {
        alert(data?.error || "Błąd przy dodawaniu taska.");
      }
    });
  }

  function initTaskActions() {
    document.addEventListener("click", async (e) => {
      const btn = e.target;
      if (!(btn instanceof Element)) return;

      const taskDiv = btn.closest(".task");
      if (!taskDiv) return;

      const taskId = taskDiv.dataset.id;

      if (btn.classList.contains("edit-task")) {
        openEditModal(taskId);
      }

      if (btn.classList.contains("delete-task")) {
        if (!confirm("Na pewno usunąć ten task?")) return;
        await deleteTask(taskId);
        await loadBoard();
      }
    });
  }

  function openEditModal(taskId) {
    const modalEl = document.getElementById("editTaskModal");
    if (!modalEl) return;
    const modal = new bootstrap.Modal(modalEl);

    const t = tasksCache.get(String(taskId));
    if (!t) return;

    document.getElementById("editTaskId").value = String(t.id);
    document.getElementById("editTaskTitle").value = t.title || "";
    document.getElementById("editTaskDesc").value = t.description || "";

    const delBtn = document.getElementById("deleteTaskBtn");
    if (delBtn) {
      delBtn.onclick = async () => {
        if (!confirm("Na pewno usunąć ten task?")) return;
        await deleteTask(t.id);
        modal.hide();
        await loadBoard();
      };
    }

    const saveBtn = document.getElementById("saveTaskBtn");
    if (saveBtn) {
      saveBtn.onclick = async () => {
        const newTitle = (document.getElementById("editTaskTitle").value || "").trim();
        const newDesc = document.getElementById("editTaskDesc").value || "";
        if (!newTitle) return alert("Tytuł nie może być pusty.");

        const body = new URLSearchParams();
        body.set("id", String(t.id));
        body.set("title", newTitle);
        body.set("description", newDesc);

        const res = await fetch("api/update_task.php", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body });
        const data = await res.json();
        if (data?.success) {
          modal.hide();
          await loadBoard();
        } else {
          alert(data?.error || "Błąd zapisu.");
        }
      };
    }

    modal.show();
  }

  async function deleteTask(taskId) {
    const body = new URLSearchParams();
    body.set("id", String(taskId));
    const res = await fetch("api/delete_task.php", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body });
    const data = await res.json();
    if (!data?.success) alert(data?.error || "Błąd usuwania.");
  }

  // init
  document.addEventListener("DOMContentLoaded", async () => {
    bindMiniToolbar(document);

    if (projectId === 0) {
      hydrateProjectsProgress();
      initCreateProject();
      return;
    }

    initAddTask();
    initTaskActions();
    await loadBoard();
  });
})();
