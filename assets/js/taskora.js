document.addEventListener("DOMContentLoaded", () => {
    const statuses = ["todo", "in_progress", "review", "done"];

    // Pobranie tasków
    fetch("api/get_tasks.php")
        .then(res => res.json())
        .then(tasks => {
            tasks.forEach(task => {
                const taskElement = document.createElement("div");
                taskElement.classList.add("task");
                taskElement.dataset.id = task.id;
                taskElement.innerHTML = `
                    <h4>${task.title}</h4>
                    <p>${task.description || ''}</p>
                    <small>Przypisane: ${task.assigned_name || 'Brak'}</small>
                `;
                document.getElementById(task.status).appendChild(taskElement);
            });
        });

    // Drag & Drop
    statuses.forEach(status => {
        new Sortable(document.getElementById(status), {
            group: 'tasks',
            animation: 150,
            onEnd: function (evt) {
                const taskId = evt.item.dataset.id;
                const newStatus = evt.to.id;

                fetch("api/update_task.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ task_id: taskId, new_status: newStatus })
                });
            }
        });
    });
});
