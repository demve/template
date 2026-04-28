<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>05 – PHP Logic in Templates</title>
  <link rel="stylesheet" href="../shared.css">
</head>
<body>

<h1>05 – PHP Logic in Templates</h1>

<p>
  The <code>output</code> section uses XML-style control-flow tags that compile to standard
  PHP alternative syntax. Use <code>&lt;dmv-php&gt;…&lt;/dmv-php&gt;</code> to assign variables or
  call functions before the block that needs them.
</p>

<h2>Tag reference</h2>
<table>
  <tr><th>Tag</th><th>Compiles to</th></tr>
  <tr><td><code>&lt;dmv-php&gt;…&lt;/dmv-php&gt;</code></td><td>Raw PHP block</td></tr>
  <tr><td><code>&lt;dmv-if test="expr"&gt;</code></td><td><code>&lt;?php if(expr): ?&gt;</code></td></tr>
  <tr><td><code>&lt;dmv-elseif test="expr"&gt;</code></td><td><code>&lt;?php elseif(expr): ?&gt;</code></td></tr>
  <tr><td><code>&lt;dmv-else&gt;</code></td><td><code>&lt;?php else: ?&gt;</code></td></tr>
  <tr><td><code>&lt;/dmv-if&gt;</code></td><td><code>&lt;?php endif; ?&gt;</code></td></tr>
  <tr><td><code>&lt;dmv-foreach on="expr as $v"&gt;</code></td><td><code>&lt;?php foreach(…): ?&gt;</code></td></tr>
  <tr><td><code>&lt;/dmv-foreach&gt;</code></td><td><code>&lt;?php endforeach; ?&gt;</code></td></tr>
  <tr><td><code>&lt;dmv-for expr="init; cond; step"&gt;</code></td><td><code>&lt;?php for(…): ?&gt;</code></td></tr>
  <tr><td><code>&lt;/dmv-for&gt;</code></td><td><code>&lt;?php endfor; ?&gt;</code></td></tr>
  <tr><td><code>&lt;dmv-while test="expr"&gt;</code></td><td><code>&lt;?php while(expr): ?&gt;</code></td></tr>
  <tr><td><code>&lt;/dmv-while&gt;</code></td><td><code>&lt;?php endwhile; ?&gt;</code></td></tr>
  <tr><td><code>&lt;dmv-render component="Name" /&gt;</code></td><td><code>$builder-&gt;render('Name')</code></td></tr>
  <tr><td><code>&lt;dmv-renderSection name="key" /&gt;</code></td><td><code>$builder-&gt;renderSection('key')</code></td></tr>
  <tr><td><code>&lt;dmv-renderSection name="key" default="val" /&gt;</code></td><td><code>$builder-&gt;renderSection('key', 'val')</code></td></tr>
  <tr><td><code>&lt;dmv-renderSectionBlock name="key" /&gt;</code></td><td><code>$builder-&gt;renderSectionBlock('key')</code></td></tr>
  <tr><td><code>&lt;dmv-renderSectionBlock name="key" modifier="m" /&gt;</code></td><td><code>$builder-&gt;renderSectionBlock('key', 'm')</code></td></tr>
</table>

<div class="note">
  Conditions like <code>count($items) &gt; 0</code> and <code>isset($a) &amp;&amp; empty($b)</code>
  work natively in <code>test=""</code> attributes. Use single quotes for PHP string literals
  inside attribute values: <code>test="$role === 'admin'"</code>.
</div>

<h2>if / elseif / else</h2>
<pre><code>&lt;dmv-php&gt;$user = $builder-&gt;get('user');&lt;/dmv-php&gt;

&lt;dmv-if test="$user['role'] === 'admin'"&gt;
  &lt;span class="badge admin"&gt;Admin&lt;/dmv-span&gt;
&lt;dmv-elseif test="$user['role'] === 'editor'"&gt;
  &lt;span class="badge editor"&gt;Editor&lt;/dmv-span&gt;
&lt;dmv-else&gt;
  &lt;span class="badge guest"&gt;Guest&lt;/dmv-span&gt;
&lt;/dmv-if&gt;</code></pre>

<h2>foreach with empty-state guard</h2>
<pre><code>&lt;dmv-php&gt;$items = $builder-&gt;get('items', []);&lt;/dmv-php&gt;
&lt;dmv-if test="count($items) &gt; 0"&gt;
  &lt;ul&gt;
    &lt;dmv-foreach on="$items as $i =&gt; $name"&gt;
      &lt;li&gt;#&lt;%$i + 1%&gt; — &lt;%$name%&gt;&lt;/dmv-li&gt;
    &lt;/dmv-foreach&gt;
  &lt;/dmv-ul&gt;
&lt;dmv-else&gt;
  &lt;p class="empty"&gt;No items found.&lt;/dmv-p&gt;
&lt;/dmv-if&gt;</code></pre>

<h2>for loop</h2>
<pre><code>&lt;dmv-for expr="$i = 1; $i &lt;= $builder-&gt;get('count', 5); $i++"&gt;
  &lt;span class="pill"&gt;item &lt;%$i%&gt;&lt;/dmv-span&gt;
&lt;/dmv-for&gt;</code></pre>

<h2>while loop</h2>
<pre><code>&lt;dmv-php&gt;$n = $builder-&gt;get('whileStart', 1);&lt;/dmv-php&gt;
&lt;while test="$n &lt;= 5"&gt;
  &lt;span class="pill"&gt;tick &lt;%$n%&gt;&lt;/dmv-span&gt;
  &lt;dmv-php&gt;$n++;&lt;/dmv-php&gt;
&lt;/dmv-while&gt;</code></pre>

<h2>Result</h2>

<iframe src="run.php" title="PHP logic output" style="height:520px"></iframe>

</body>
</html>
