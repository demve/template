<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>01 – Basic Usage</title>
  <link rel="stylesheet" href="../shared.css">
</head>
<body>

<h1>01 – Basic Usage</h1>

<p>
  The minimum setup: instantiate <code>Template</code> with a components directory and a
  cache directory, push some variables, then call <code>render()</code>.
</p>

<h2>1. Instantiate</h2>
<pre><code>$t = new Template(
    componentsDir: __DIR__ . '/components',
    cacheDir:      __DIR__ . '/cache'
);</code></pre>
<p>
  The cache directory is created automatically. It stores two PHP files per component:
  a <em>load cache</em> (raw HTML with directives replaced by PHP calls) and an
  <em>output cache</em> (the compiled <code>output</code> section, ready to include).
</p>

<h2>2. Pass variables</h2>
<pre><code>$t->set('name', 'World');
$t->set('year', date('Y'));</code></pre>
<p>
  Variables set here are available inside any component via
  <code>$builder->get('key')</code> or the comment directive
  <code>&lt;!--print::key--&gt;</code>.
</p>

<h2>3. Comment directives</h2>
<pre><code>&lt;!-- inside greeting.html --&gt;
&lt;h1&gt;Hello, &lt;!--print::name--&gt;!&lt;/h1&gt;</code></pre>
<p>
  Directives use HTML comment syntax so IDEs highlight and format the file as normal HTML.
  <code>&lt;!--fn::arg--&gt;</code> compiles to <code>&lt;?php $builder->fn("arg"); ?&gt;</code>.
  Multi-arg: <code>&lt;!--fn::arg1|arg2--&gt;</code>.
</p>

<h2>4. Render</h2>
<pre><code>$t->render('Greeting');   // echoes directly
// or
$html = $t->render('Greeting', [], return: true);  // captures</code></pre>

<div class="note">
  <strong>Component naming:</strong> <code>Greeting</code> maps to
  <code>components/greeting.html</code>. <code>PageHeader</code> maps to
  <code>components/page/header.html</code>. PascalCase is split on capital letters.
</div>

<h2>Result</h2>

<iframe src="run.php" title="Basic example output" style="height:260px"></iframe>

</body>
</html>
