<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.0.2/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet"
    integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
  <title>{{ $settings['application_name'] }}</title>
  @vite('resources/js/app.js')

  <script>
    function reload() {
      window.location.reload();
    }
  </script>

</head>

<body data-settings='{!! json_encode($settings) !!}'>
  <style id="erugo-css-variables">
    :root {
      --primary-color: {{ $settings['css_primary_color'] }};
      --secondary-color: {{ $settings['css_secondary_color'] }};
      --accent-color: {{ $settings['css_accent_color'] }};
      --accent-color-light: {{ $settings['css_accent_color_light'] }};
    }
  </style>
  <div class="share-not-ready">
    <div class="share-status-inner">
      <h1><svg class="lucide lucide-circle-dashed" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
          viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
          stroke-linejoin="round">
          <path d="M10.1 2.182a10 10 0 0 1 3.8 0" />
          <path d="M13.9 21.818a10 10 0 0 1-3.8 0" />
          <path d="M17.609 3.721a10 10 0 0 1 2.69 2.7" />
          <path d="M2.182 13.9a10 10 0 0 1 0-3.8" />
          <path d="M20.279 17.609a10 10 0 0 1-2.7 2.69" />
          <path d="M21.818 10.1a10 10 0 0 1 0 3.8" />
          <path d="M3.721 6.391a10 10 0 0 1 2.7-2.69" />
          <path d="M6.391 20.279a10 10 0 0 1-2.69-2.7" />
        </svg>Share is being processed</h1>
      <p>Try again in a few moments.</p>
      <button onclick="reload()">Try again now</button>
    </div>
  </div>
</body>

</html>
