<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>09 – File Modifiers</title>
  <link rel="stylesheet" href="../shared.css">
</head>
<body>

<h1>09 – File Modifiers</h1>

<p>
  The <code>CssFileModifier</code> and <code>JsFileModifier</code> generate combined, minified static files 
  for production use. They use a hash-based filename and check file modification times 
  for cache busting.
</p>

<h2>How it works</h2>
<ol>
  <li>
    You add the modifiers pointing to a public output directory and public URL prefix.
  </li>
  <li>
    When you use <code>&lt;!--=files::css--&gt;</code>, the modifier evaluates all 
    component dependencies, minifies the CSS, and creates a combined file like 
    <code>[hash].css</code>.
  </li>
  <li>
    It outputs a cache-busting URL (e.g., <code>[hash].css?v=[timestamp]</code>) or 
    the raw file contents directly (if <code>read_file</code> is true).
  </li>
</ol>

<h2>Configuration</h2>
<pre><code>$t->addModifier('css', new CssFileModifier([
    'output_dir' => __DIR__ . '/public/assets',
    'public_url_prefix' => 'public/assets',
    'read_file' => false
]));</code></pre>

<h2>Usage</h2>
<p>
  Use the <code>files</code> directive. With the shorthand syntax, 
  <code>&lt;!--=files::css--&gt;</code> is equivalent to 
  <code>&lt;!--=files::css|css--&gt;</code>.
</p>

<pre><code>&lt;head&gt;
    &lt;link rel="stylesheet" href="&lt;!--=files::css--&gt;"&gt;
&lt;/head&gt;
&lt;body&gt;
    &lt;script src="&lt;!--=files::js--&gt;"&gt;&lt;/script&gt;
&lt;/body&gt;
</code></pre>

<h2>Result</h2>
<p>
  The <code>run.php</code> file outputs the layout, and you can verify the statically 
  generated assets inside the <code>public/assets</code> directory.
</p>

<iframe src="run.php" title="File Modifiers Output" style="height:520px;width:100%; border: 1px solid #ddd;"></iframe>

</body>
</html>
