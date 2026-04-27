<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>03 – ModifierInterface</title>
  <style>
    body  { font-family: sans-serif; max-width: 860px; margin: 2rem auto; padding: 0 1rem; color: #222; }
    h1    { border-bottom: 2px solid #4f46e5; padding-bottom: .4rem; }
    h2    { margin-top: 2rem; color: #4f46e5; }
    pre   { background: #f5f5f5; border-left: 4px solid #4f46e5; padding: 1rem; overflow-x: auto; border-radius: 4px; }
    code  { font-family: monospace; font-size: .9rem; }
    iframe { width: 100%; height: 220px; border: 1px solid #ddd; border-radius: 6px; margin-top: 1rem; }
    .note { background: #fffbe6; border-left: 4px solid #f59e0b; padding: .75rem 1rem; border-radius: 4px; }
  </style>
</head>
<body>

<h1>03 – ModifierInterface</h1>

<p>
  <code>renderSectionBlock()</code> accepts an optional second argument that implements
  <code>ModifierInterface</code>. The modifier receives the <em>combined</em> output of
  all items in the section as a single string and returns the processed result.
</p>

<h2>The contract</h2>
<pre><code>interface ModifierInterface
{
    // $sections is ['ComponentName' => 'content', ...]
    public function process(array $sections): string;
}</code></pre>
<p>
  The modifier receives the <strong>raw sections array</strong> keyed by component name.
  It decides how to combine and transform them — the library never concatenates for you.
  Output: whatever string you want rendered.
</p>

<h2>Example: CSS minifier</h2>
<pre><code>class CssMinifier implements ModifierInterface
{
    public function process(array $sections): string
    {
        $combined = implode("\n", $sections);  // combine first
        $combined = preg_replace('/\/\*.*?\*\//s', '', $combined);
        $combined = preg_replace('/\s+/', ' ', $combined);
        $combined = preg_replace('/\s*([{}:;,])\s*/', '$1', $combined);
        return trim($combined);
    }
}</code></pre>

<h2>Registering and using a modifier</h2>
<pre><code>// run.php — register once by key
$t->addModifier('css', new CssMinifier());
$t->addModifier('js',  new JsMinifier());</code></pre>
<pre><code>&lt;!-- layout.html — reference by key, no PHP instantiation in component files --&gt;
&lt;style&gt;@renderSectionBlock('style', 'css')&lt;/style&gt;
&lt;script&gt;@renderSectionBlock('script', 'js')&lt;/script&gt;</code></pre>
<p>
  The modifier key is a plain string inside the component file.
  This keeps <code>.html</code> files free of PHP class names and makes it easy to swap
  implementations (e.g. switch from a regex minifier to a proper parser) without touching templates.
  If the key is not registered the section falls back to a plain <code>implode("\n")</code>.
</p>

<div class="note">
  <strong>Receiving the array matters:</strong> a modifier that gets the array can annotate
  each item with its origin, reorder by specificity, deduplicate, or generate source maps.
  A modifier that only got a combined string would have lost that information.
</div>

<h2>Other uses</h2>
<ul>
  <li>JS bundler / minifier for <code>renderSectionBlock('script')</code></li>
  <li>CSS autoprefixer</li>
  <li>Markdown renderer (collect markdown sections, render them all at once)</li>
  <li>Content Security Policy hash generator</li>
</ul>

<h2>Result — view source to see the minified CSS</h2>

<iframe src="run.php" title="Modifier output"></iframe>

</body>
</html>
