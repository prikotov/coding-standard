# Test Document

## Section One

Some text with a [valid link](subdir/target.md).

A [broken link](subdir/missing.md) to nowhere.

A link to [same-dir](sibling.md) file.

## Section Two

A link with [anchor](subdir/target.md#section-one) to another file.

A link with [broken anchor](subdir/target.md#missing-section) to existing file.

A [local anchor](#section-two) in this file.

A [broken local anchor](#nonexistent-section).

A [self-reference](#test-document) to top heading.

## Section Three

Reference-style link: [ref-link][ref-id] and [another ref][second-id].

[ref-id]: subdir/target.md
[second-id]: subdir/target.md#section-one

A [broken ref][broken-ref-id].

[broken-ref-id]: subdir/missing.md

### Duplicate Heading

Some content.

### Duplicate Heading

More content.

A link to [duplicate heading](#duplicate-heading-1).

A link to [russian anchor](subdir/target.md#русский-заголовок).
