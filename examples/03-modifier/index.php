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
    public function process(string $content): string;
}</code></pre>
<p>
  One method. Input: the concatenated section content from all loaded components.
  Output: whatever you want rendered — minified CSS, bundled JS, a hash, anything.
</p>

<h2>Example: CSS minifier</h2>
<pre><code>class CssMinifier implements ModifierInterface
{
    public function process(string $content): string
    {
        $content = preg_replace('/\/\*.*?\*\//s', '', $content);  // strip comments
        $content = preg_replace('/\s+/', ' ', $content);           // collapse whitespace
        $content = preg_replace('/\s*([{}:;,])\s*/', '$1', $content);
        return trim($content);
    }
}</code></pre>

<h2>Using it in a layout</h2>
<pre><code>&lt;style&gt;@renderSectionBlock('style', new CssMinifier())&lt;/style&gt;</code></pre>
<p>
  The modifier runs <strong>once</strong> on the full combined CSS — not once per component.
  This means it can see across component boundaries, which matters for minifiers that
  strip redundant selectors or for tools that generate source maps.
</p>

<div class="note">
  <strong>Recommended pattern:</strong> instantiate modifiers outside the template and
  pass them in via <code>$data</code>, or register them on the <code>Template</code>
  instance, rather than using <code>new CssMinifier()</code> inside the component file.
  That keeps component files free of PHP class instantiation.
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
