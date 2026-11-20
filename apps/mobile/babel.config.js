// Babel for the REOS mobile app. Unistyles' plugin makes every StyleSheet in
// src/ react to theme, text-size and breakpoint changes without re-rendering.
module.exports = function (api) {
  api.cache(true);

  return {
    presets: ['babel-preset-expo'],
    plugins: [['react-native-unistyles/plugin', { root: 'src' }]],
  };
};
