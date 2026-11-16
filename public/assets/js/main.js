(function () {
    "use strict";

    document.querySelectorAll("[data-toggle-password]").forEach(function (button) {
        button.addEventListener("click", function () {
            var selector = button.getAttribute("data-toggle-password");
            var input = selector ? document.querySelector(selector) : null;

            if (!input) {
                return;
            }

            var showing = input.getAttribute("type") === "text";
            input.setAttribute("type", showing ? "password" : "text");
            button.textContent = showing ? "Show" : "Hide";
            button.setAttribute("aria-pressed", showing ? "false" : "true");
        });
    });

    document.addEventListener("click", function (event) {
        var link = event.target.closest("a[data-confirm]");

        if (!link) {
            return;
        }

        if (!window.confirm(link.getAttribute("data-confirm") || "Are you sure?")) {
            event.preventDefault();
        }
    });

    document.addEventListener("submit", function (event) {
        var submitter = event.submitter;
        var source = submitter && submitter.hasAttribute("data-confirm") ? submitter : null;

        if (!source && event.target.hasAttribute("data-confirm")) {
            source = event.target;
        }

        if (!source) {
            return;
        }

        if (!window.confirm(source.getAttribute("data-confirm") || "Are you sure?")) {
            event.preventDefault();
        }
    });

    document.querySelectorAll("[data-flash]").forEach(function (alert) {
        var close = alert.querySelector("[data-flash-close]");

        function dismiss() {
            alert.classList.add("is-dismissed");
            window.setTimeout(function () {
                alert.remove();
            }, 200);
        }

        if (close) {
            close.addEventListener("click", dismiss);
        }

        window.setTimeout(dismiss, 8000);
    });

    var toggle = document.querySelector("[data-nav-toggle]");
    var nav = document.querySelector("#site-nav");

    if (toggle && nav) {
        toggle.addEventListener("click", function () {
            var open = nav.classList.toggle("is-open");
            toggle.setAttribute("aria-expanded", open ? "true" : "false");
        });

        nav.querySelectorAll("a").forEach(function (link) {
            link.addEventListener("click", function () {
                nav.classList.remove("is-open");
                toggle.setAttribute("aria-expanded", "false");
            });
        });
    }

    var search = document.querySelector("[data-user-filter]");

    if (!search) {
        return;
    }

    var table = document.querySelector(search.getAttribute("data-user-filter"));
    var empty = document.querySelector("[data-filter-empty]");

    if (!table) {
        return;
    }

    var rows = Array.prototype.slice.call(table.querySelectorAll("tbody tr"));

    search.addEventListener("input", function () {
        var query = search.value.trim().toLowerCase();
        var visible = 0;

        rows.forEach(function (row) {
            var text = (row.getAttribute("data-search") || row.textContent || "").toLowerCase();
            var match = query === "" || text.indexOf(query) !== -1;
            row.hidden = !match;
            if (match) {
                visible += 1;
            }
        });

        if (empty) {
            empty.hidden = visible !== 0;
        }
    });
}());
