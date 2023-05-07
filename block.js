const { registerBlockType } = wp.blocks;
const { TextControl, Button } = wp.components;
const { createElement, useState } = wp.element;

registerBlockType('rs-stream/video-block', {
    title: 'RS Stream Video Block',
    icon: 'video-alt3',
    category: 'media',
    attributes: {
        videoId: {
            type: 'string',
            default: '',
        },
    },

    edit: function(props) {
        const { attributes, setAttributes } = props;
        const { videoId } = attributes;
        const [isLoading, setIsLoading] = useState(false);
        const [videoHtml, setVideoHtml] = useState(null);

        function onChangeVideoId(newValue) {
            setAttributes({ videoId: newValue });
        }

       function onEmbedClick() {
		setIsLoading(true);
		console.log('Video ID:', attributes.videoId);

		fetch('/wp-json/rs-stream/v1/generate-token', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': wpApiSettings.nonce,
			},
			body: JSON.stringify({
				videoId: attributes.videoId,
			}),
		})
			.then((response) => {
				setIsLoading(false);

				if (response.ok) {
					return response.json();
				} else {
					throw new Error('Error generating token');
				}
			})
			.then((data) => {
				if (data.video_html) {
					setVideoHtml(data.video_html);
				}
			})
			.catch((error) => {
				console.error('Error generating token:', error);
			});
		}

        return createElement(
            'div',
            {},
            createElement(TextControl, {
                label: 'Enter Video ID',
                value: videoId,
                onChange: onChangeVideoId,
            }),
            createElement(Button, {
                isPrimary: true,
                onClick: onEmbedClick,
                disabled: isLoading,
            }, 'Embed'),
            createElement('div', {
                dangerouslySetInnerHTML: { __html: videoHtml },
            })
        );
    },

    save: function() {
        // We will use server-side rendering for the output.
        return null;
    },
});
