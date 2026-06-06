import { createFileRoute, Link } from '@tanstack/react-router'
import { useRef, useEffect, useState } from 'react'
import { EditorView, basicSetup } from 'codemirror'
import { StreamLanguage } from '@codemirror/language'
import { shell } from '@codemirror/legacy-modes/mode/shell'
import { oneDark } from '@codemirror/theme-one-dark'
import { EditorState } from '@codemirror/state'

export const Route = createFileRoute('/playground')({
  component: PlaygroundPage,
})

const PRESET_IMAGES = ['alpine:3.19', 'ubuntu:24.04', 'debian:12-slim', 'composer:2', 'node:20-alpine', 'python:3.12-slim']

const DEFAULT_SCRIPT = `#!/bin/sh
echo "Hello from container!"
echo "OS: $(uname -a)"
echo "Date: $(date)"
`

interface Result {
  success: boolean
  output: string
  durationMs: number
}

function PlaygroundPage() {
  const editorRef = useRef<HTMLDivElement>(null)
  const viewRef = useRef<EditorView | null>(null)
  const [image, setImage] = useState('alpine:3.19')
  const [customImage, setCustomImage] = useState('')
  const [running, setRunning] = useState(false)
  const [result, setResult] = useState<Result | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!editorRef.current) return

    const view = new EditorView({
      state: EditorState.create({
        doc: DEFAULT_SCRIPT,
        extensions: [
          basicSetup,
          StreamLanguage.define(shell),
          oneDark,
          EditorView.theme({
            '&': { height: '100%', fontSize: '13px' },
            '.cm-scroller': { fontFamily: 'ui-monospace, monospace', overflow: 'auto' },
          }),
        ],
      }),
      parent: editorRef.current,
    })
    viewRef.current = view
    return () => view.destroy()
  }, [])

  const effectiveImage = customImage.trim() || image

  async function handleRun() {
    if (!viewRef.current) return
    const script = viewRef.current.state.doc.toString().trim()
    if (!script || !effectiveImage) return

    setRunning(true)
    setResult(null)
    setError(null)

    try {
      const res = await fetch('/playground/run', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ image: effectiveImage, script }),
      })
      const data = await res.json()
      if (!res.ok) {
        setError(data.error ?? data.message ?? 'Request failed')
      } else {
        setResult(data)
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Network error')
    } finally {
      setRunning(false)
    }
  }

  return (
    <div className="min-h-screen bg-gray-950 flex flex-col">
      {/* header */}
      <div className="px-6 py-4 border-b border-gray-800 flex items-center gap-4">
        <Link to="/pipelines" className="text-gray-500 hover:text-gray-300 text-xs flex items-center gap-1">
          ← Pipelines
        </Link>
        <div className="w-px h-4 bg-gray-800" />
        <div>
          <h1 className="text-white text-base font-semibold">Script Playground</h1>
          <p className="text-gray-500 text-xs mt-0.5">Run a shell script in any Docker image</p>
        </div>
      </div>

      <div className="flex flex-1 overflow-hidden" style={{ height: 'calc(100vh - 116px)' }}>
        {/* left: editor */}
        <div className="flex flex-col w-1/2 border-r border-gray-800">
          {/* image picker */}
          <div className="flex items-center gap-2 px-4 py-3 border-b border-gray-800 bg-gray-900/40 flex-wrap">
            <span className="text-gray-400 text-xs">Image</span>
            <div className="flex flex-wrap gap-1.5">
              {PRESET_IMAGES.map(img => (
                <button
                  key={img}
                  onClick={() => { setImage(img); setCustomImage('') }}
                  className={`px-2 py-0.5 rounded text-xs font-mono transition-colors ${
                    image === img && !customImage
                      ? 'bg-blue-600 text-white'
                      : 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-gray-200'
                  }`}
                >
                  {img}
                </button>
              ))}
            </div>
            <input
              type="text"
              placeholder="or type custom…"
              value={customImage}
              onChange={e => setCustomImage(e.target.value)}
              className="ml-1 bg-gray-800 border border-gray-700 rounded px-2 py-0.5 text-xs text-gray-200 placeholder-gray-600 focus:outline-none focus:border-blue-500 w-40"
            />
          </div>

          {/* codemirror */}
          <div ref={editorRef} className="flex-1 overflow-hidden" />

          {/* run bar */}
          <div className="flex items-center justify-between px-4 py-3 border-t border-gray-800 bg-gray-900/40">
            <span className="text-gray-500 text-xs font-mono">{effectiveImage}</span>
            <button
              onClick={handleRun}
              disabled={running}
              className="flex items-center gap-2 px-4 py-1.5 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-medium rounded-md transition-colors"
            >
              {running && (
                <svg className="animate-spin w-3 h-3" viewBox="0 0 24 24" fill="none">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
              )}
              {running ? 'Running…' : 'Run'}
            </button>
          </div>
        </div>

        {/* right: output */}
        <div className="flex flex-col w-1/2 bg-gray-950">
          {/* status bar */}
          {(result || error || running) && (
            <div className={`flex items-center gap-2 px-4 py-2 border-b border-gray-800 text-xs ${
              running ? 'bg-blue-950/40 text-blue-300'
              : result?.success ? 'bg-green-950/40 text-green-400'
              : 'bg-red-950/40 text-red-400'
            }`}>
              {running && <span className="inline-block w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse" />}
              {!running && result?.success && <span className="inline-block w-1.5 h-1.5 rounded-full bg-green-500" />}
              {!running && (error || result && !result.success) && <span className="inline-block w-1.5 h-1.5 rounded-full bg-red-500" />}
              <span>
                {running ? 'Running…'
                  : result?.success ? `Exited 0 · ${(result.durationMs / 1000).toFixed(1)}s`
                  : error ? error
                  : `Exited non-zero · ${((result?.durationMs ?? 0) / 1000).toFixed(1)}s`}
              </span>
            </div>
          )}

          {/* output */}
          <div className="flex-1 overflow-auto p-4">
            {!result && !error && !running && (
              <p className="text-gray-600 text-xs font-mono">Output will appear here after you click Run.</p>
            )}
            {running && (
              <p className="text-gray-500 text-xs font-mono animate-pulse">Pulling image and executing…</p>
            )}
            {(result || error) && (
              <pre className="text-gray-200 text-xs font-mono whitespace-pre-wrap break-words leading-relaxed">
                {error ?? result?.output}
              </pre>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
