<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>06 – File-Based CSS Cache</title>
  <link rel="stylesheet" href="../shared.css">
</head>
<body>

<h1>06 – File-Based CSS Cache</h1>

<p>
  Standard modifiers receive the combined section content as a string.
  However, for production assets, it's often better to save the result to a physical file
  on disk and return a <code>&lt;link&gt;</code> tag.
</p>

<h2>FileModifierInterface</h2>
<p>
  By implementing <code>FileModifierInterface</code>, your <code>processFiles()</code> method
  receives an array of <strong>absolute paths</strong> to the section cache files instead of their content.
</p>

<pre><code>interface FileModifierInterface extends ModifierInterface
{
    /** @param array<string, string> $files [ComponentName => AbsolutePath] */
    public function processFiles(array $files, Template $template): string;
}</code></pre>

<h2>Caching Strategy</h2>
<ol>
  <li>
    <strong>Check Freshness:</strong> Compare the <code>mtime</code> of your output file (e.g., <code>styles.css</code>)
    against the <code>mtime</code> of all section cache files.
  </li>
  <li>
    <strong>Fast Path:</strong> If <code>styles.css</code> is newer than all sections, return the 
    <code>&lt;link&gt;</code> tag immediately. No files are read, no regex is run.
  </li>
  <li>
    <strong>Slow Path:</strong> If any section is newer, read them all, minify, write to 
    <code>styles.css</code>, and then return the tag.
  </li>
</ol>

<h2>Component Usage</h2>
<pre><code>&lt;!-- layout.html --&gt;
&lt;head&gt;
  &lt;!--=blockFiles::style|css-file--&gt;
&lt;/head&gt;</code></pre>

<p>
  <code>blockFiles()</code> behaves like <code>block()</code> but specifically targets
  <code>FileModifierInterface</code> implementations to pass paths instead of strings.
</p>

<h2>Implementation Details</h2>
<pre><code>public function processFiles(array $files, Template $template): string
{
    if (file_exists($this->outputFile) && $this->isFresh($files)) {
        return $this->linkTag();
    }

    // Only read and process if stale
    $css = implode("\n", array_map(fn($f) => $template->fetchFile($f), $files));
    $css = $this->minify($css);
    file_put_contents($this->outputFile, $css);

    return $this->linkTag();
}</code></pre>

<div class="note">
  This pattern allows you to leverage <strong>Browser Caching</strong> and 
  <strong>CDN distribution</strong> for your CSS/JS while keeping the development 
  experience of "edit and refresh".
</div>

<h2>Result</h2>
<iframe src="run.php" title="CSS File Cache output" style="height:200px"></iframe>

</body>
</html>
