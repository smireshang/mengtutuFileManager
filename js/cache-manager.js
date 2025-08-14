/**
 * 文件缓存管理器
 * 提供浏览器端文件缓存功能，减少服务器请求
 */
class FileCacheManager {
  constructor() {
    this.cachePrefix = "fileCache_"
    this.metaPrefix = "fileMeta_"
    this.defaultExpireTime = 24 * 60 * 60 * 1000 // 24小时
    this.maxCacheSize = 50 * 1024 * 1024 // 50MB
    this.init()
  }

  init() {
    // 清理过期缓存
    this.cleanExpiredCache()
    // 检查缓存大小
    this.checkCacheSize()
  }

  /**
   * 生成缓存键
   */
  getCacheKey(filename) {
    return this.cachePrefix + encodeURIComponent(filename)
  }

  /**
   * 生成元数据键
   */
  getMetaKey(filename) {
    return this.metaPrefix + encodeURIComponent(filename)
  }

  /**
   * 设置文件缓存
   */
  setCache(filename, data, fileModified, contentType = "text/plain") {
    try {
      const cacheKey = this.getCacheKey(filename)
      const metaKey = this.getMetaKey(filename)

      const cacheData = {
        data: data,
        contentType: contentType,
        cached: Date.now(),
        expires: Date.now() + this.defaultExpireTime,
        fileModified: fileModified,
        size: this.getDataSize(data),
      }

      // 检查是否有足够空间
      if (!this.hasEnoughSpace(cacheData.size)) {
        this.clearOldestCache()
      }

      localStorage.setItem(cacheKey, JSON.stringify(cacheData))

      // 更新元数据
      this.updateCacheMeta(filename, cacheData.size, cacheData.cached)

      console.log(`文件 ${filename} 已缓存`)
      return true
    } catch (error) {
      console.error("缓存设置失败:", error)
      return false
    }
  }

  /**
   * 获取文件缓存
   */
  getCache(filename, fileModified) {
    try {
      const cacheKey = this.getCacheKey(filename)
      const cachedData = localStorage.getItem(cacheKey)

      if (!cachedData) {
        return null
      }

      const cache = JSON.parse(cachedData)

      // 检查是否过期
      if (Date.now() > cache.expires) {
        this.removeCache(filename)
        return null
      }

      // 检查文件是否被修改
      if (fileModified && cache.fileModified !== fileModified) {
        this.removeCache(filename)
        return null
      }

      console.log(`从缓存加载文件: ${filename}`)
      return cache
    } catch (error) {
      console.error("缓存读取失败:", error)
      this.removeCache(filename)
      return null
    }
  }

  /**
   * 移除文件缓存
   */
  removeCache(filename) {
    const cacheKey = this.getCacheKey(filename)
    const metaKey = this.getMetaKey(filename)

    localStorage.removeItem(cacheKey)
    localStorage.removeItem(metaKey)

    console.log(`已清除文件缓存: ${filename}`)
  }

  /**
   * 清理过期缓存
   */
  cleanExpiredCache() {
    const now = Date.now()
    const keysToRemove = []

    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i)
      if (key && key.startsWith(this.cachePrefix)) {
        try {
          const data = JSON.parse(localStorage.getItem(key))
          if (data.expires && now > data.expires) {
            keysToRemove.push(key)
            // 同时移除对应的元数据
            const filename = decodeURIComponent(key.replace(this.cachePrefix, ""))
            keysToRemove.push(this.getMetaKey(filename))
          }
        } catch (error) {
          keysToRemove.push(key)
        }
      }
    }

    keysToRemove.forEach((key) => localStorage.removeItem(key))

    if (keysToRemove.length > 0) {
      console.log(`清理了 ${keysToRemove.length / 2} 个过期缓存`)
    }
  }

  /**
   * 检查缓存大小并清理
   */
  checkCacheSize() {
    const totalSize = this.getTotalCacheSize()
    if (totalSize > this.maxCacheSize) {
      this.clearOldestCache()
    }
  }

  /**
   * 获取总缓存大小
   */
  getTotalCacheSize() {
    let totalSize = 0
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i)
      if (key && key.startsWith(this.cachePrefix)) {
        try {
          const data = JSON.parse(localStorage.getItem(key))
          totalSize += data.size || 0
        } catch (error) {
          // 忽略损坏的缓存项
        }
      }
    }
    return totalSize
  }

  /**
   * 清理最旧的缓存
   */
  clearOldestCache() {
    const cacheItems = []

    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i)
      if (key && key.startsWith(this.cachePrefix)) {
        try {
          const data = JSON.parse(localStorage.getItem(key))
          cacheItems.push({
            key: key,
            filename: decodeURIComponent(key.replace(this.cachePrefix, "")),
            cached: data.cached || 0,
            size: data.size || 0,
          })
        } catch (error) {
          // 移除损坏的缓存项
          localStorage.removeItem(key)
        }
      }
    }

    // 按缓存时间排序，移除最旧的
    cacheItems.sort((a, b) => a.cached - b.cached)

    let removedSize = 0
    const targetSize = this.maxCacheSize * 0.7 // 清理到70%

    for (const item of cacheItems) {
      if (this.getTotalCacheSize() <= targetSize) {
        break
      }

      this.removeCache(item.filename)
      removedSize += item.size
    }

    if (removedSize > 0) {
      console.log(`清理了 ${this.formatBytes(removedSize)} 的旧缓存`)
    }
  }

  /**
   * 更新缓存元数据
   */
  updateCacheMeta(filename, size, cached) {
    const metaKey = this.getMetaKey(filename)
    const meta = {
      size: size,
      cached: cached,
      filename: filename,
    }
    localStorage.setItem(metaKey, JSON.stringify(meta))
  }

  /**
   * 检查是否有足够空间
   */
  hasEnoughSpace(newDataSize) {
    const currentSize = this.getTotalCacheSize()
    return currentSize + newDataSize <= this.maxCacheSize
  }

  /**
   * 获取数据大小（字节）
   */
  getDataSize(data) {
    if (typeof data === "string") {
      return new Blob([data]).size
    }
    return JSON.stringify(data).length * 2 // 粗略估算
  }

  /**
   * 格式化字节大小
   */
  formatBytes(bytes, precision = 2) {
    const units = ["B", "KB", "MB", "GB"]
    let size = bytes
    let unitIndex = 0

    while (size > 1024 && unitIndex < units.length - 1) {
      size /= 1024
      unitIndex++
    }

    return size.toFixed(precision) + " " + units[unitIndex]
  }

  /**
   * 获取缓存统计信息
   */
  getCacheStats() {
    let totalFiles = 0
    let totalSize = 0

    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i)
      if (key && key.startsWith(this.cachePrefix)) {
        try {
          const data = JSON.parse(localStorage.getItem(key))
          totalFiles++
          totalSize += data.size || 0
        } catch (error) {
          // 忽略损坏的缓存项
        }
      }
    }

    return {
      totalFiles: totalFiles,
      totalSize: totalSize,
      formattedSize: this.formatBytes(totalSize),
      maxSize: this.formatBytes(this.maxCacheSize),
      usagePercent: ((totalSize / this.maxCacheSize) * 100).toFixed(1),
    }
  }

  /**
   * 清空所有缓存
   */
  clearAllCache() {
    const keysToRemove = []

    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i)
      if (key && (key.startsWith(this.cachePrefix) || key.startsWith(this.metaPrefix))) {
        keysToRemove.push(key)
      }
    }

    keysToRemove.forEach((key) => localStorage.removeItem(key))
    console.log(`已清空所有缓存，共 ${keysToRemove.length} 项`)
  }

  /**
   * 预加载文件到缓存
   */
  async preloadFile(filename, fileModified) {
    // 检查是否已缓存
    if (this.getCache(filename, fileModified)) {
      return true
    }

    try {
      // 根据文件类型选择加载方式
      const extension = filename.split(".").pop().toLowerCase()
      const isImage = ["jpg", "jpeg", "png", "gif"].includes(extension)
      const isText = ["txt"].includes(extension)

      if (isImage) {
        return await this.preloadImage(filename, fileModified)
      } else if (isText) {
        return await this.preloadText(filename, fileModified)
      }
    } catch (error) {
      console.error(`预加载文件失败 ${filename}:`, error)
      return false
    }
  }

  /**
   * 预加载图片
   */
  async preloadImage(filename, fileModified) {
    return new Promise((resolve) => {
      const img = new Image()
      img.crossOrigin = "anonymous"

      img.onload = () => {
        try {
          const canvas = document.createElement("canvas")
          const ctx = canvas.getContext("2d")
          canvas.width = img.width
          canvas.height = img.height
          ctx.drawImage(img, 0, 0)

          const dataUrl = canvas.toDataURL()
          this.setCache(filename, dataUrl, fileModified, "image")
          resolve(true)
        } catch (error) {
          console.error("图片缓存失败:", error)
          resolve(false)
        }
      }

      img.onerror = () => {
        console.error("图片加载失败:", filename)
        resolve(false)
      }

      img.src = `serve_file.php?file=${encodeURIComponent(filename)}&t=${Date.now()}`
    })
  }

  /**
   * 预加载文本文件
   */
  async preloadText(filename, fileModified) {
    try {
      const response = await fetch(`preview.php?file=${encodeURIComponent(filename)}&ajax=1&t=${Date.now()}`)
      if (response.ok) {
        const text = await response.text()
        this.setCache(filename, text, fileModified, "text/plain")
        return true
      }
    } catch (error) {
      console.error("文本文件预加载失败:", error)
    }
    return false
  }
}

// 创建全局缓存管理器实例
window.fileCacheManager = new FileCacheManager()
