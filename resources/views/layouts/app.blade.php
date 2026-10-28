<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('judul') - Lostracer UPH</title>

    <!-- Font Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-navy: #1E3A8A;
            --emerald-found: #059669;
            --rose-lost: #E11D48;
            --bg-light: #F8FAFC;
            --text-dark: #0F172A;
            --border-color: #E2E8F0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            background-color: var(--bg-light);
            color: var(--text-dark);
        }

        nav {
            background-color: var(--primary-navy);
            padding: 14px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        nav .brand {
            color: #ffffff;
            font-weight: 700;
            font-size: 18px;
            text-decoration: none;
            letter-spacing: 0.5px;
        }

        nav .menu a, nav .menu button {
            color: #ffffff;
            margin-left: 18px;
            text-decoration: none;
            background: none;
            border: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }

        main {
            max-width: 960px;
            margin: 30px auto;
            background: #ffffff;
            padding: 28px;
            border-radius: 10px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .alert-success {
            background-color: #ECFDF5;
            color: var(--emerald-found);
            border: 1px solid #A7F3D0;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-danger {
            background-color: #FFF1F2;
            color: var(--rose-lost);
            border: 1px solid #FECDD3;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 14px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="date"],
        input[type="file"],
        select,
        textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            box-sizing: border-box;
            font-family: inherit;
            font-size: 14px;
        }

        button.btn-primary {
            background-color: var(--primary-navy);
            color: #ffffff;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
        }

        @media print {
            nav, .no-print { display: none !important; }
            body { background: #ffffff; }
            main { margin: 0; padding: 0; box-shadow: none; max-width: 100%; }
        }
    </style>
</head>
<body>

    {{-- Navigasi HANYA ditampilkan jika pengguna sudah login --}}
    @auth
    <nav>
        <a href="/dashboard" class="brand">Lostracer</a>
        <div class="menu">
            <a href="/dashboard">Dashboard</a>
            <a href="/items/create">+ Lapor Barang</a>
            <form action="/logout" method="POST" style="display: inline;">
                @csrf
                <button type="submit">Logout ({{ auth()->user()->name }})</button>
            </form>
        </div>
    </nav>
    @endauth

    <main>
        @if(session('success'))
            <div class="alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert-danger">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="alert-danger">
                <ul style="margin: 0; padding-left: 20px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('konten')
    </main>

</body>
</html>