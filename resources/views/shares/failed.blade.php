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
      <h1>Bad Error</h1>
      <p>Something went horribly wrong with this share. <br><small>Please ask the sender to try again.</small></p>
    </div>
  </div>
</body>

</html>
