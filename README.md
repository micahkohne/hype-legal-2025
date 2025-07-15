# Task Runner
Front-end tool for task automation.

## Dependencies
1. [NodeJS](http://nodejs.org/) v18+ 
2. [NPM](https://www.npmjs.com/) 

Please make sure to use npm package manager and Node.js v18+.

## Install
In the root directory of the project run:

```
npm install
```

## Development
To start the project in development mode, run:

```
npm run dev
```


## Build
To build the project for production, run:

```
npm run build
```

## Preview production files
To locally preview the production build run:

```
npm run preview
```

---

## How to view pages

You need to enter the page name in the address bar.

For example:

```
http://my-ip:8000/about.html
```

### Dev process
There is an `index.html` file that shows the contents of your dev env (i.e. a pretty server listing).

This file doesn't get built.

## How to load assets

### HTML assets

Assets loaded from build/assets where build is the root:

```
<img src="assets/images/my-image.png" alt="">
```

Loading vendor CSS or JS:

```
<link rel="stylesheet" href="/assets/vendor/my-style.css" type="text/css" media="all" />
<script type="text/javascript" src="/assets/vendor/my-script.js"></script>
```

### CSS assets

Fonts start from build with a slash (/):

```
url('/assets/fonts/my-font.woff') format('woff');
```

The path for CSS background images start from assets as well.

```
background: url(/assets/images/images/temp/logo.png) 0 0 no-repeat;
```

### AJAX assets

Use `./` in your local AJAX URLs.

```
<a href="./ajax/popup.html" class="js-popup">Popup</a>
```

---

Responsive mixin disclaimer:

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

======

### Missing fetures:

* Autoprefixer - note that there is no autoprefixer. You'll need to handle vendor prefixes yourself when needed.
