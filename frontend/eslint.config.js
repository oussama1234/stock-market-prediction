// ESLint Flat Config - minimal, single export
import js from '@eslint/js'
import globals from 'globals'

export default [
  {
    ignores: ['dist/**', 'node_modules/**'],
  },
  js.configs.recommended,
  {
    files: ['**/*.{js,jsx}'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: { ...globals.browser, ...globals.node },
    },
    rules: {
      'no-unused-vars': ['warn', { argsIgnorePattern: '^_', varsIgnorePattern: '^[A-Z_]' }],
      'no-undef': 'warn',
    },
  },
  {
    files: ['tailwind.config.js', 'vite.config.*', 'postcss.config.*', 'test-integration.js'],
    languageOptions: { globals: { ...globals.node } },
    rules: { 'no-undef': 'off', 'no-unused-vars': ['warn', { argsIgnorePattern: '^_' }] },
  },
]
