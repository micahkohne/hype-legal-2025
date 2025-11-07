<?php

/**
 * -------- GLOBAL NAMESPACE: EE helper functions used all over --------
 */

namespace {
    if (!function_exists('ee')) {
        /**
         * Very-lightweight EE container stub for static analysis only.
         */
        function ee(): object
        {
            static $stub;
            if ($stub) {
                return $stub;
            }

            $stub = new class {
                public function lang(...$args)
                {
                    return null;
                }
                public function config(...$args): object
                {
                    return new class {
                        public function item(string $key, $default = null)
                        {
                            return $default;
                        }
                    };
                }
                public function session(...$args): object
                {
                    return new class {
                        public function userdata(string $key)
                        {
                            return null;
                        }
                        public function set_flashdata(string $key, $val): void
                        {
                        }
                    };
                }
                public function cp(...$args): object
                {
                    return new class {
                        public function add_to_head(string $html): void
                        {
                        }
                        public function add_to_foot(string $html): void
                        {
                        }
                        public function set_right_nav(array $items): void
                        {
                        }
                        public function set_breadcrumb(string $url, string $label): void
                        {
                        }
                    };
                }
                public function db(...$args): object
                {
                    // super-skinny fluent stub
                    return new class {
                        public function __call(string $name, array $args)
                        {
                            return $this;
                        }
                        public function get($table = null)
                        {
                            return $this;
                        }
                        public function result_array(): array
                        {
                            return [];
                        }
                        public function row_array(): array
                        {
                            return [];
                        }
                        public function escapeStr($s)
                        {
                            return $s;
                        }
                    };
                }
                public function output(...$args): object
                {
                    return new class {
                        public function show_message(array $cfg): void
                        {
                        }
                        public function send_ajax_response($data): void
                        {
                        }
                    };
                }
                public function functions(...$args): object
                {
                    return new class {
                        public function redirect(string $url): void
                        {
                        }
                    };
                }
                public function view(...$args): object
                {
                    return new class {
                        public function make(string $tpl, array $data = [])
                        {
                            return $this;
                        }
                        public function render(): string
                        {
                            return '';
                        }
                    };
                }
            };

            return $stub;
        }
    }

    if (!function_exists('lang')) {
        function lang(string $key, ...$args): string
        {
            return $key;
        }
    }

    if (!function_exists('cp_url')) {
        function cp_url(string $path = '', array $qs = []): string
        {
            return '/cp/' . ltrim($path, '/');
        }
    }
}

/**
 * -------- EE class stubs you reference by FQCN --------
 */
namespace ExpressionEngine\Service\Model {
    class Model
    {
    }
}

namespace ExpressionEngine\Service\View {
    class ViewFactory
    {
        public function make(string $tpl, array $data = []): object
        {
            return new class {
                public function render(): string
                {
                    return '';
                }
            };
        }
    }
}
