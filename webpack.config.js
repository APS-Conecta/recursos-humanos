const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

// entry 'main' → js/recursos_humanos-main.js, the bundle PageController adds.
webpackConfig.entry = {
	main: path.join(__dirname, 'src', 'main.js'),
}

module.exports = webpackConfig
