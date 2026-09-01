const csrfToken = () =>
    document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content') || '';

export const sendRoomViewerHeartbeat = async (gameId: number) => {
    const response = await fetch(`/games/${gameId}/viewers`, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({}),
        credentials: 'same-origin',
    });

    if (!response.ok) return null;

    return (await response.json()) as { viewer_count: number };
};
