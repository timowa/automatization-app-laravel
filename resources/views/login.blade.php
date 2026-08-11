
@extends('layout')

@section('title', 'Авторизация')

@section('content')
    <div class="flex justify-center">
        <div class="w-full max-w-md bg-white p-8 rounded shadow">
            <h2 class="text-2xl font-bold mb-6 text-center">Авторизация</h2>

            @if (!empty($error))
                <div class="mb-4 p-3 bg-red-100 text-red-700 rounded">{{ $error }}</div>
            @endif

            <form action="/login" method="POST">
                @csrf
                <div class="mb-4">
                    <label for="login" class="block text-sm font-medium mb-1">Логин</label>
                    <input type="text" id="login" name="login" required autofocus class="w-full border rounded px-3 py-2">
                </div>
                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium mb-1">Пароль</label>
                    <input type="password" id="password" name="password" required class="w-full border rounded px-3 py-2">
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">Войти</button>
            </form>
        </div>
    </div>
@endsection
