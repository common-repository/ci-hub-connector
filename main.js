const baseUrl = 'https://ci-hub.azurewebsites.net/client/wordpress/index.html'
const iFrameId = '_com_ci_hub_iframe'
const tabId = 'cihub'
const tabTitle = 'CI HUB'
const mediaFrameRouterEntry = { [tabId]: { text: tabTitle, priority: 60 } }
const menuItemId = `menu-item-${tabId}`

jQuery(document).ready(function($) {
  if (wp.media) {
    // Add the tab to "post" frame
    const oldMediaFramePost = wp.media.view.MediaFrame.Post
    wp.media.view.MediaFrame.Post = oldMediaFramePost.extend({
      browseRouter(routerView) {
        oldMediaFrameSelect.prototype.browseRouter.apply(this, arguments)
        routerView.set(mediaFrameRouterEntry)
      }
    })

    // Add the tab to "select" frame
    const oldMediaFrameSelect = wp.media.view.MediaFrame.Select
    wp.media.view.MediaFrame.Select = oldMediaFrameSelect.extend({
      browseRouter(routerView) {
        oldMediaFrameSelect.prototype.browseRouter.apply(this, arguments)
        routerView.set(mediaFrameRouterEntry)
      }
    })

    // Get element by selector using last element (3rd party plugins handle content differently)
    const getLastElementByQuerySelector = (selector) => {
      const elements = document.querySelectorAll(selector)
      if (elements.length) return elements[elements.length - 1]
    }

    // Get the media router wrapper
    const getMediaRouterWrapper = () => getLastElementByQuerySelector('[class="media-router"]')

    const setIFrame = () => {
      const iFrameHTML = `<iframe id="${iFrameId}" src="${baseUrl}" style="border: 0;" width="100%" height="99%"></iframe>`
      const contentElement = getLastElementByQuerySelector(`[aria-labelledby="${menuItemId}"]`) || getLastElementByQuerySelector(`[class="media-frame-content"]`)
      if (contentElement && contentElement.innerHTML !== iFrameHTML) contentElement.innerHTML = iFrameHTML
    }

    const getUploadButton = () => getLastElementByQuerySelector('[id="com-ci-hub-upload-from-ci-hub-button"]')

    // Handle tab content
    wp.media.view.Modal.prototype.on('open', function() {
      const searchMediaRouterForMenuItem = () => { // Used as a fallback for old wordpress versions
        const mediaRouterWrapper = getMediaRouterWrapper()
        if (mediaRouterWrapper) {
          let index = 0
          for (let item of mediaRouterWrapper.children) {
            if (item.innerHTML === tabTitle) return item
            index++
          }
        }
      }
      const addMenuEventListeners = () => {
        const menuItemElement = getLastElementByQuerySelector(`[id="${menuItemId}"]`) || searchMediaRouterForMenuItem()
        if (menuItemElement) menuItemElement.addEventListener('click', () => setIFrame())
        setTimeout(() => { if (menuItemElement && menuItemElement.className.includes('active')) { setTimeout(() => setIFrame(), 100) } }, 100)

        const uploadButton = getUploadButton()
        if (uploadButton) uploadButton.addEventListener('click', () => {
          const frame = wp.media.frame
          if (frame) frame.content.mode(tabId)
        })
      }
      addMenuEventListeners()
      const mediaModal = getLastElementByQuerySelector('div.media-modal')
      if (mediaModal) mediaModal.addEventListener('click', () => addMenuEventListeners())

      const selectButton = document.querySelector('.media-button-select')
      if (selectButton) {
        selectButton.addEventListener('click', () => window.location.href.includes('/upload.php') && window.location.reload())
      }
    })

    let ciHub
    window.addEventListener('message', async (event) => {
      const iFrame = getLastElementByQuerySelector(`[id="${iFrameId}"]`)
      const errorHandler = (error) => iFrame.contentWindow.postMessage({ id, error: { message: error.message || error.responseText || 'Unknown error', stack: error.stack || '' } }, '*')
      const { id, type, data } = event.data
      if (!iFrame || !id || !type) return
      let result
      try {
        switch (type) {
          case 'init': {
            ciHub = eval(data)
            break
          }
          case 'getPluginVersion': {
            iFrame.contentWindow.postMessage({ id, data: '1.2.104' }, '*')
            break
          }
          default: {
            const fct = ciHub && ciHub[type]
            if (!fct) throw new Error(`WordPress.ui.onmessage unexpected message type: ${type}`)
            result = await fct(data)
            break
          }
        }
        iFrame.contentWindow.postMessage({ id, data: result }, '*')
      } catch (error) { errorHandler(error) }
    })

    // Handle upload button
    const uploadButton = getUploadButton()
    if (uploadButton) uploadButton.addEventListener('click', () => {
      const frame = wp.media({ title: 'Upload media', button: { text: 'Close' }, multiple: false })
      frame.open()
      frame.content.mode(tabId)
      const mediaRouterWrapper = getMediaRouterWrapper()
      if (mediaRouterWrapper) {
        let index = 0
        for (let item of mediaRouterWrapper.children) {
          if (item.id === menuItemId) mediaRouterWrapper.innerHTML = mediaRouterWrapper.children.item(index).outerHTML
          index++
        }
        setIFrame()
      }
    })
  }
})