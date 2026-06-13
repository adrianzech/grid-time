// @ts-check
import stylistic from '@stylistic/eslint-plugin'
import withNuxt from './.nuxt/eslint.config.mjs'

export default withNuxt({
  ignores: ['**/temp/**', '**/public/**'], plugins: {
    '@stylistic': stylistic,
  },

  rules: {
    '@stylistic/comma-dangle': ['error', 'always-multiline'],
    '@stylistic/arrow-parens': ['error', 'always'],
    '@stylistic/brace-style': ['error', '1tbs', { allowSingleLine: true }],
  },
})
  .override('nuxt/vue/rules', {
    rules: {
      'vue/multi-word-component-names': 'off', 'vue/max-attributes-per-line': ['error', {
        singleline: { max: 3 }, multiline: { max: 1 },
      }],
    },
  })
  .override('nuxt/typescript/rules', {
    rules: {
      '@typescript-eslint/no-explicit-any': 'warn',
    },
  })
