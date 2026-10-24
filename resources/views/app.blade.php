<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cat Advisor</title>
    {{--
        Arizona Bootstrap 5.2.2, the university's brand CSS. It isn't
        on npm, so it loads from the university CDN. The integrity value is a
        hash of that exact file: if the CDN ever served something else, the
        browser would refuse it. Update the hash when you change the version.
    --}}
    <link rel="stylesheet"
          href="https://cdn.digital.arizona.edu/lib/arizona-bootstrap/5.2.2/css/arizona-bootstrap.min.css"
          integrity="sha384-wI4ZFF+Q17zFOfJSPmczqtkEMnRkxoFPPtk3Kg4c37lozE3jYmmJ/N+EipHy1p6n"
          crossorigin="anonymous">
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
