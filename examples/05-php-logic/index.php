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
  The <code>output</code> section (and any other section) is executed as a standard PHP file.
  You have full access to PHP's control structures like <code>if</code>, <code>foreach</code>, 
  and <code>while</code>.
</p>

<h2>Reference</h2>
<table>
  <tr><th>Task</th><th>Syntax</th></tr>
  <tr><td>Logic (if, for, etc.)</td><td>Standard <code>&lt;?php ... ?&gt;</code></td></tr>
  <tr><td>Echo escaped value</td><td><code>&lt;?= $this->e($var) ?&gt;</code> or <code>&lt;!--=print::var--&gt;</code></td></tr>
  <tr><td>Render sub-component</td><td><code>&lt;?= $this->render('Name', $data) ?&gt;</code></td></tr>
  <tr><td>Render slot (layout)</td><td><code>&lt;!--=slot::name--&gt;</code> or <code>&lt;?= $this->slot('name') ?&gt;</code></td></tr>
  <tr><td>Render block (layout)</td><td><code>&lt;!--=block::name--&gt;</code> or <code>&lt;?= $this->block('name') ?&gt;</code></td></tr>
</table>

<div class="note">
  Inside any component, the Template instance is available via <code>$this</code>.
</div>

<h2>if / elseif / else</h2>
<pre><code>&lt;!--section::output--&gt;
&lt;?php $user = $this->get('user'); ?&gt;

&lt;?php if ($user['role'] === 'admin'): ?&gt;
  &lt;span class="badge admin"&gt;Admin&lt;/span&gt;
&lt;?php elseif ($user['role'] === 'editor'): ?&gt;
  &lt;span class="badge editor"&gt;Editor&lt;/span&gt;
&lt;?php else: ?&gt;
  &lt;span class="badge guest"&gt;Guest&lt;/span&gt;
&lt;?php endif; ?&gt;</code></pre>

<h2>foreach with empty-state guard</h2>
<pre><code>&lt;?php $items = $this->get('items', []); ?&gt;
&lt;?php if (count($items) > 0): ?&gt;
  &lt;ul&gt;
    &lt;?php foreach ($items as $i => $name): ?&gt;
      &lt;li&gt;#&lt;?= $i + 1 ?&gt; — &lt;?= $this->e($name) ?&gt;&lt;/li&gt;
    &lt;?php endforeach; ?&gt;
  &lt;/ul&gt;
&lt;?php else: ?&gt;
  &lt;p class="empty"&gt;No items found.&lt;/p&gt;
&lt;?php endif; ?&gt;</code></pre>

<h2>for loop</h2>
<pre><code>&lt;?php for ($i = 1; $i <= $this->get('count', 5); $i++): ?&gt;
  &lt;span class="pill"&gt;item &lt;?= $i ?&gt;&lt;/span&gt;
&lt;?php endfor; ?&gt;</code></pre>

<h2>while loop</h2>
<pre><code>&lt;?php $n = $this->get('whileStart', 1); ?&gt;
&lt;?php while ($n <= 5): ?&gt;
  &lt;span class="pill"&gt;tick &lt;?= $n ?&gt;&lt;/span&gt;
  &lt;?php $n++; ?&gt;
&lt;?php endwhile; ?&gt;</code></pre>

<h2>Result</h2>

<iframe src="run.php" title="PHP logic output" style="height:520px"></iframe>

</body>
</html>
