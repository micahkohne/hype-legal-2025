module.exports = {
  languageOptions: {
    parserOptions: {
      ecmaVersion: 2020,
      sourceType: 'module',
    },
    globals: { 
      '$': 'writable' 
    },
  },
  settings: {
    react: {
      version: 'detect'
    }
  },
  rules: {
    'no-console': 2,
    'no-unused-vars': ['error', {
      'ignoreRestSiblings': true
    }],
  },
};
