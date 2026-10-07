<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="min-h-screen">
        @if (session('is_admin'))
            <nav class="bg-white shadow">
                <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
                    <div class="flex gap-6">
                        <a href="/agents" class="font-bold text-lg">Агенты</a>
                        <a href="/posts" class="font-bold text-lg">Посты</a>
                    </div>
                    <div class="flex items-center gap-4">
                        @include('partials.version-menu')
                        <form action="/logout" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-sm text-red-600 hover:underline">Выйти</button>
                        </form>
                    </div>
                </div>
            </nav>
        @endif

        <main class="w-full max-w-9/10 mx-auto px-4 py-6">
            @yield('content')
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            (function initProductVersionMenu() {
                const root = document.getElementById('product-version-menu');
                if (!root) return;
                const toggle = document.getElementById('product-version-toggle');
                const dropdown = document.getElementById('product-version-dropdown');
                if (!toggle || !dropdown) return;

                let versionMarkedSeen = false;

                const markVersionSeen = function () {
                    if (versionMarkedSeen) return;
                    versionMarkedSeen = true;

                    const badge = document.getElementById('version-new-badge');
                    if (badge) {
                        badge.remove();
                    }

                    const url = root.getAttribute('data-seen-url');
                    const csrf = root.getAttribute('data-csrf');
                    if (!url || !csrf) return;

                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'Content-Type': 'application/json',
                        },
                        body: '{}',
                        credentials: 'same-origin',
                    }).catch(function (err) {
                        console.error(err);
                        versionMarkedSeen = false;
                    });
                };

                let closeTimer = null;

                const open = () => {
                    if (closeTimer) {
                        clearTimeout(closeTimer);
                        closeTimer = null;
                    }
                    dropdown.classList.remove('hidden');
                    toggle.setAttribute('aria-expanded', 'true');
                };
                const close = () => {
                    dropdown.classList.add('hidden');
                    toggle.setAttribute('aria-expanded', 'false');
                };
                const scheduleClose = () => {
                    if (closeTimer) {
                        clearTimeout(closeTimer);
                    }
                    closeTimer = setTimeout(close, 150);
                };

                toggle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    markVersionSeen();
                    if (dropdown.classList.contains('hidden')) {
                        open();
                    } else {
                        close();
                    }
                });
                root.addEventListener('mouseenter', open);
                root.addEventListener('mouseleave', scheduleClose);
                document.addEventListener('click', function (e) {
                    if (!root.contains(e.target)) {
                        close();
                    }
                });
            })();

            document.querySelectorAll('form.ajaxForm').forEach(function (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const formData = new FormData(form);
                    fetch(form.action, {
                        method: form.method,
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    })
                    .then(r => r.json())
                    .then(data => {
                        let messages = data.messages || [];
                        if (!messages.length && data.errors) {
                            messages = Object.values(data.errors).flat();
                        }
                        if (!messages.length && data.message) {
                            messages = [data.message];
                        }
                        if (messages.length) {
                            alert(messages.join('\n'));
                        }
                        if (data.redirect) {
                            setTimeout(() => window.location.href = data.redirect, 1500);
                        }
                    })
                    .catch(err => {
                        alert('Ошибка запроса');
                        console.error(err);
                    });
                });
            });

            window.deleteAgent = function (name, formId) {
                const password = prompt('Введите пароль Руденко для удаления агента "' + name + '"');
                if (password === null) return;
                const form = document.getElementById(formId);
                form.querySelector('input[name="password"]').value = password;
                form.dispatchEvent(new Event('submit'));
            };
        });
    </script>
    @stack('scripts')
</body>
</html>
