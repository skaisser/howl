/**
 * build-llms-full.mjs
 *
 * Generates llms-full.txt by concatenating all Markdown pages from docs/
 * (excluding .vitepress internals) with ## File: <path> headers and --- separators.
 *
 * Also copies llms.txt and the generated llms-full.txt into docs/public/ so
 * VitePress serves them at the site root (howl.skaisser.dev/llms.txt etc.).
 *
 * Run: npm run docs:build:llms
 */

import { readFileSync, writeFileSync, readdirSync, statSync, mkdirSync, copyFileSync, existsSync } from 'fs'
import { join, relative, resolve } from 'path'
import { fileURLToPath } from 'url'

const __dirname = fileURLToPath(new URL('.', import.meta.url))
const ROOT = resolve(__dirname, '../../..')       // repo root
const DOCS = resolve(__dirname, '../..')           // docs/
const PUBLIC = resolve(DOCS, 'public')             // docs/public/
const OUT_ROOT = resolve(ROOT, 'llms-full.txt')    // repo root copy
const OUT_PUBLIC = resolve(PUBLIC, 'llms-full.txt') // docs/public/ copy

// Ensure docs/public/ exists
if (!existsSync(PUBLIC)) {
  mkdirSync(PUBLIC, { recursive: true })
}

/**
 * Recursively collect all .md files under a directory,
 * excluding .vitepress/ internals.
 */
function collectMarkdownFiles(dir) {
  const entries = readdirSync(dir)
  const files = []

  for (const entry of entries) {
    const full = join(dir, entry)
    const stat = statSync(full)

    if (stat.isDirectory()) {
      // Skip .vitepress internals
      if (entry === '.vitepress') continue
      // Skip node_modules if somehow nested
      if (entry === 'node_modules') continue
      files.push(...collectMarkdownFiles(full))
    } else if (entry.endsWith('.md')) {
      files.push(full)
    }
  }

  // Sort for deterministic output
  return files.sort()
}

const markdownFiles = collectMarkdownFiles(DOCS)

const llmsTxtPath = resolve(ROOT, 'llms.txt')
const llmsTxtContent = existsSync(llmsTxtPath)
  ? readFileSync(llmsTxtPath, 'utf8')
  : ''

const sections = []

// Prepend llms.txt index header
if (llmsTxtContent) {
  sections.push('## File: llms.txt\n\n' + llmsTxtContent.trim())
}

// Append each Markdown page
for (const file of markdownFiles) {
  const rel = relative(ROOT, file)
  const content = readFileSync(file, 'utf8').trim()
  sections.push(`## File: ${rel}\n\n${content}`)
}

const output = sections.join('\n\n---\n\n') + '\n'

// Write to both destinations
writeFileSync(OUT_ROOT, output, 'utf8')
writeFileSync(OUT_PUBLIC, output, 'utf8')

console.log(`✓ llms-full.txt written (${markdownFiles.length} pages, ${output.split('\n').length} lines)`)
console.log(`  → ${OUT_ROOT}`)
console.log(`  → ${OUT_PUBLIC}`)

// Copy llms.txt into docs/public/ so VitePress serves it at the site root
if (existsSync(llmsTxtPath)) {
  copyFileSync(llmsTxtPath, resolve(PUBLIC, 'llms.txt'))
  console.log(`✓ llms.txt copied to docs/public/llms.txt`)
}
