import { defineConfig } from 'vite';
import { resolve } from 'path';
import { glob } from 'glob';
import externalGlobals from 'rollup-plugin-external-globals';
import handlebars from 'vite-plugin-handlebars';
import createExternal from 'vite-plugin-external';
import sassGlobImports from 'vite-plugin-sass-glob-import';
import { watchAndRun } from 'vite-plugin-watch-and-run';
import { directoryPlugin } from 'vite-plugin-list-directory-contents';
import htmlPurge from 'vite-plugin-html-purgecss';

const root = resolve(__dirname, 'src');
const outDir = resolve(__dirname, 'web/build');
const allHtmlFiles = glob.sync(resolve(root, './*.html').replace(/\\/g, '/'));
const htmlFilesToBuild = allHtmlFiles.filter((file) => !file.includes('index.html')); // Excludes index.html from the build array so we don't build an emtpy index.

export default defineConfig({
	base: '',
	root,
	server: {
		open: '/',
		host: '127.0.0.1',
	},
	build: {
		outDir,
		rollupOptions: {
			input: htmlFilesToBuild,
			output: {
				assetFileNames: (assetInfo) => {
					const extType = assetInfo.name.split('.').pop();
					const isCSS = extType == 'css';

					return isCSS ? `assets/style.[ext]` : `assets/[name].[ext]`; // Hacky solution to fix issues with build file names.
				},
				entryFileNames: () => {
					const areMoreThanOneHTMLs = htmlFilesToBuild.length > 1;

					return areMoreThanOneHTMLs ? `assets/[name].js` : 'assets/app.js'; // Hacky solution to fix issues with build file names.
				},
				chunkFileNames: 'assets/app.js',
			},
		},
		emptyOutDir: true,
		minify: true,
	},
	css: {
		preprocessorOptions: {
			scss: {
				api: 'modern-compiler',
				silenceDeprecations: ['import', 'global-builtin']
			}
		}
	},
	plugins: [
		directoryPlugin({
			baseDir: root,
		}),
		handlebars({
			partialDirectory: resolve(root, 'partials'),
		}),
		externalGlobals(
			{
				jquery: 'jQuery',
			},
			{
				include: ['*.js', '*.ts', '*.jsx', '*.tsx', '*.vue'],
			}
		),
		createExternal({
			externals: {
				jquery: 'jQuery',
			},
		}),
		htmlPurge.default(),
		sassGlobImports(),
		watchAndRun([
			{
				watch: '**/scss/**/*.scss',
				watchKind: 'add',
				run: 'touch ./src/scss/style.scss',
				quiet: true,
			},
		]),
	],
});
