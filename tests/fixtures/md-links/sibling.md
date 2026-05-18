# Sibling File

A [link back](root.md) to root (same directory).

Code block should be ignored:

```markdown
[not a real link](subdir/missing-in-code.md)
```

```
[another fake link](nowhere.md#broken-anchor)
```

~~~markdown
[tilde code block](also-ignored.md)
~~~

Inline code `[not parsed](fake.md)` should also be ignored — it's inside backticks.

External URLs are skipped: [Google](https://google.com) and [email](mailto:test@test.com).

A [deliberately broken link](no-such-file.md) for testing.
