// Toggle formularza dodawania taska
document.getElementById("toggleFormBtn").addEventListener("click", () => {
    document.getElementById("addTaskForm").classList.toggle("hidden");
});




document.getElementById("addTaskBtn").addEventListener("click", function() {
    let title = document.getElementById("taskTitle").value;
    let description = document.getElementById("taskDesc").value;
    if(title.trim() === "") {
        alert("Podaj tytuł zadania!");
        return;
    }
    fetch("api/create_task.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "title=" + encodeURIComponent(title) + "&description=" + encodeURIComponent(description)
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            let taskList = document.getElementById("ready");
            let newTask = document.createElement("div");
            newTask.classList.add("task");
            newTask.setAttribute("data-id", data.id);
            newTask.innerHTML = `<h3>${title}</h3><p>${description}</p>
              <div class="task-actions">
                <button class="edit-task">✏️</button>
                <button class="delete-task">🗑️</button>
              </div>`;
            taskList.appendChild(newTask);
            document.getElementById("taskTitle").value = "";
            document.getElementById("taskDesc").value = "";
        } else {
            alert("Błąd przy dodawaniu zadania!");
        }
    });
});

// Edycja i usuwanie tasków
document.addEventListener("click", function(e) {
    let taskDiv = e.target.closest(".task");
    if(!taskDiv) return;
    let taskId = taskDiv.dataset.id;

    if(e.target.classList.contains("edit-task")) {
        let newTitle = prompt("Nowy tytuł:", taskDiv.querySelector("h3").innerText);
        if(newTitle) {
            let newDesc = prompt("Nowy opis:", taskDiv.querySelector("p").innerText);
            fetch("api/update_task.php", {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: "id=" + taskId + "&title=" + encodeURIComponent(newTitle) + "&description=" + encodeURIComponent(newDesc)
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    taskDiv.querySelector("h3").innerText = newTitle;
                    taskDiv.querySelector("p").innerText = newDesc;
                } else {
                    alert("Błąd przy edycji!");
                }
            });
        }
    }

    if(e.target.classList.contains("delete-task")) {
        if(confirm("Na pewno usunąć ten task?")) {
            fetch("api/delete_task.php", {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: "id=" + taskId
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    taskDiv.remove();
                } else {
                    alert("Błąd przy usuwaniu!");
                }
            });
        }
    }
});




// Drag & Drop z SortableJS
document.querySelectorAll(".task-list").forEach(list => {
    new Sortable(list, {
        group: "shared", // pozwala przenosić pomiędzy listami
        animation: 150,
        onEnd: function (evt) {
            let taskId = evt.item.dataset.id;
            let newStatus = evt.to.id;

            // Wyślij do API nowy status
            fetch("api/update_task.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "id=" + taskId + "&status=" + newStatus
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    alert("Błąd przy zmianie statusu!");
                }
            });
        }
    });
});





