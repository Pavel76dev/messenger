import { useEffect, useRef, useState } from 'react';
import {
  Alert,
  Avatar,
  Box,
  Button,
  Dialog,
  DialogContent,
  IconButton,
  Stack,
  Tooltip,
  Typography,
} from '@mui/material';
import CallEndIcon from '@mui/icons-material/CallEnd';
import CallIcon from '@mui/icons-material/Call';
import MicIcon from '@mui/icons-material/Mic';
import MicOffIcon from '@mui/icons-material/MicOff';
import ScreenShareIcon from '@mui/icons-material/ScreenShare';
import StopScreenShareIcon from '@mui/icons-material/StopScreenShare';
import VideocamIcon from '@mui/icons-material/Videocam';
import VideocamOffIcon from '@mui/icons-material/VideocamOff';
import { Room, RoomEvent, Track } from 'livekit-client';
import { useAppDispatch, useAppSelector } from '../../app/hooks';
import {
  acceptCall,
  endCall,
  joinCall,
  rejectCall,
  resetCallState,
  setCallError,
} from './callsSlice';
import { resolveCallId } from './callUtils';
import { canScreenShare, screenShareHint } from './screenShare';

function getInitials(name) {
  if (!name) return '?';
  return name
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() || '')
    .join('');
}

function ParticipantTile({ name, avatarUrl, videoEl, audioEl, isScreen }) {
  return (
    <Box
      sx={{
        position: 'relative',
        bgcolor: 'grey.900',
        borderRadius: 2,
        overflow: 'hidden',
        minHeight: isScreen ? 220 : 160,
        flex: 1,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
      }}
    >
      <Box
        component="video"
        ref={videoEl}
        autoPlay
        playsInline
        muted={false}
        sx={{
          width: '100%',
          height: '100%',
          objectFit: isScreen ? 'contain' : 'cover',
          display: 'block',
          position: 'absolute',
          inset: 0,
        }}
      />
      <Box component="audio" ref={audioEl} autoPlay playsInline sx={{ display: 'none' }} />
      <Stack
        direction="row"
        spacing={1}
        alignItems="center"
        sx={{
          position: 'absolute',
          left: 8,
          bottom: 8,
          bgcolor: 'rgba(0,0,0,0.55)',
          color: 'common.white',
          px: 1,
          py: 0.5,
          borderRadius: 1,
        }}
      >
        <Avatar src={avatarUrl || undefined} sx={{ width: 24, height: 24, fontSize: 12 }}>
          {getInitials(name)}
        </Avatar>
        <Typography variant="caption">{name}</Typography>
        {isScreen && (
          <Typography variant="caption" sx={{ opacity: 0.8 }}>
            · экран
          </Typography>
        )}
      </Stack>
    </Box>
  );
}

function useAttachTrack(track, elRef) {
  useEffect(() => {
    const el = elRef.current;
    if (!el || !track) return undefined;
    track.attach(el);
    return () => {
      track.detach(el);
    };
  }, [track, elRef]);
}

function RemoteParticipantView({ participant }) {
  const cameraVideoRef = useRef(null);
  const screenVideoRef = useRef(null);
  const audioRef = useRef(null);
  const [cameraTrack, setCameraTrack] = useState(null);
  const [screenTrack, setScreenTrack] = useState(null);
  const [audioTrack, setAudioTrack] = useState(null);

  useEffect(() => {
    const sync = () => {
      let cam = null;
      let screen = null;
      let audio = null;
      participant.trackPublications.forEach((pub) => {
        if (!pub.track) return;
        if (pub.source === Track.Source.Camera && pub.kind === Track.Kind.Video) {
          cam = pub.track;
        }
        if (pub.source === Track.Source.ScreenShare && pub.kind === Track.Kind.Video) {
          screen = pub.track;
        }
        if (pub.kind === Track.Kind.Audio && pub.source !== Track.Source.ScreenShareAudio) {
          audio = pub.track;
        }
      });
      setCameraTrack(cam);
      setScreenTrack(screen);
      setAudioTrack(audio);
    };

    sync();
    participant.on('trackSubscribed', sync);
    participant.on('trackUnsubscribed', sync);
    participant.on('trackMuted', sync);
    participant.on('trackUnmuted', sync);

    return () => {
      participant.off('trackSubscribed', sync);
      participant.off('trackUnsubscribed', sync);
      participant.off('trackMuted', sync);
      participant.off('trackUnmuted', sync);
    };
  }, [participant]);

  useAttachTrack(cameraTrack, cameraVideoRef);
  useAttachTrack(screenTrack, screenVideoRef);
  useAttachTrack(audioTrack, audioRef);

  const name = participant.name || participant.identity;

  return (
    <Stack spacing={1} sx={{ flex: 1, minWidth: { xs: '100%', sm: 240 } }}>
      {screenTrack && (
        <ParticipantTile
          name={name}
          videoEl={screenVideoRef}
          audioEl={{ current: null }}
          isScreen
        />
      )}
      <ParticipantTile
        name={name}
        videoEl={cameraVideoRef}
        audioEl={audioRef}
        isScreen={false}
      />
    </Stack>
  );
}

function LocalPreview({ room, mediaType }) {
  const videoRef = useRef(null);
  const [hasVideo, setHasVideo] = useState(false);

  useEffect(() => {
    if (!room) return undefined;

    const sync = () => {
      const pub = room.localParticipant.getTrackPublication(Track.Source.Camera);
      const track = pub?.track;
      const el = videoRef.current;
      if (el && track) {
        track.attach(el);
        setHasVideo(true);
      } else {
        setHasVideo(false);
      }
    };

    sync();
    room.localParticipant.on('localTrackPublished', sync);
    room.localParticipant.on('localTrackUnpublished', sync);

    return () => {
      room.localParticipant.off('localTrackPublished', sync);
      room.localParticipant.off('localTrackUnpublished', sync);
      const el = videoRef.current;
      const pub = room.localParticipant.getTrackPublication(Track.Source.Camera);
      if (el && pub?.track) {
        pub.track.detach(el);
      }
    };
  }, [room]);

  if (mediaType !== 'video') {
    return null;
  }

  return (
    <Box
      sx={{
        position: 'absolute',
        right: 16,
        bottom: 88,
        width: 120,
        height: 160,
        borderRadius: 2,
        overflow: 'hidden',
        bgcolor: 'grey.800',
        border: '2px solid',
        borderColor: 'common.white',
        zIndex: 2,
        display: hasVideo ? 'block' : 'none',
      }}
    >
      <Box
        component="video"
        ref={videoRef}
        autoPlay
        playsInline
        muted
        sx={{ width: '100%', height: '100%', objectFit: 'cover', transform: 'scaleX(-1)' }}
      />
    </Box>
  );
}

export function CallOverlay() {
  const dispatch = useAppDispatch();
  const { session, incoming, error } = useAppSelector((state) => state.calls);
  const currentUser = useAppSelector((state) => state.auth.user);
  const [room, setRoom] = useState(null);
  const [remotes, setRemotes] = useState([]);
  const [micOn, setMicOn] = useState(true);
  const [camOn, setCamOn] = useState(true);
  const [sharing, setSharing] = useState(false);
  const [connecting, setConnecting] = useState(false);
  const roomRef = useRef(null);
  const shareSupported = canScreenShare();

  useEffect(() => {
    if (!session?.token || !session?.livekit_url) {
      return undefined;
    }

    let cancelled = false;
    const nextRoom = new Room({
      adaptiveStream: true,
      dynacast: true,
    });

    const refreshRemotes = () => {
      setRemotes(Array.from(nextRoom.remoteParticipants.values()));
    };

    nextRoom.on(RoomEvent.ParticipantConnected, refreshRemotes);
    nextRoom.on(RoomEvent.ParticipantDisconnected, refreshRemotes);
    nextRoom.on(RoomEvent.TrackSubscribed, refreshRemotes);
    nextRoom.on(RoomEvent.TrackUnsubscribed, refreshRemotes);
    nextRoom.on(RoomEvent.Disconnected, () => {
      if (!cancelled) {
        setRoom(null);
      }
    });

    (async () => {
      setConnecting(true);
      try {
        await nextRoom.connect(session.livekit_url, session.token);
        if (cancelled) {
          await nextRoom.disconnect();
          return;
        }
        const wantVideo = session.call?.media_type === 'video';
        await nextRoom.localParticipant.setMicrophoneEnabled(true);
        await nextRoom.localParticipant.setCameraEnabled(wantVideo);
        setMicOn(true);
        setCamOn(wantVideo);
        roomRef.current = nextRoom;
        setRoom(nextRoom);
        refreshRemotes();
      } catch (err) {
        console.error(err);
        const raw = String(err?.message || err || '');
        const isPc =
          /could not establish pc connection/i.test(raw) ||
          /pc connection/i.test(raw) ||
          /ICE/i.test(raw);
        dispatch(
          setCallError(
            isPc
              ? 'Нет медиа-соединения с LiveKit (ICE). Перезапустите: docker compose -f docker/livekit/docker-compose.yml up -d --force-recreate'
              : raw ||
                  'Не удалось подключиться к LiveKit. Проверьте docker/livekit и LIVEKIT_URL.',
          ),
        );
      } finally {
        if (!cancelled) {
          setConnecting(false);
        }
      }
    })();

    return () => {
      cancelled = true;
      nextRoom.disconnect();
      roomRef.current = null;
      setRoom(null);
      setRemotes([]);
      setSharing(false);
    };
  }, [session?.token, session?.livekit_url, session?.call?.id, session?.call?.media_type, dispatch]);

  const handleHangup = async () => {
    const callId = resolveCallId(session?.call?.id);
    if (roomRef.current) {
      try {
        await roomRef.current.disconnect();
      } catch (_) {
        // ignore
      }
    }
    // Сначала сбрасываем UI, чтобы трубки снова появились
    dispatch(resetCallState());
    if (callId != null) {
      dispatch(endCall(callId));
    }
  };

  const toggleMic = async () => {
    if (!room) return;
    const next = !micOn;
    await room.localParticipant.setMicrophoneEnabled(next);
    setMicOn(next);
  };

  const toggleCam = async () => {
    if (!room) return;
    const next = !camOn;
    await room.localParticipant.setCameraEnabled(next);
    setCamOn(next);
  };

  const toggleShare = async () => {
    if (!room || !shareSupported) return;
    try {
      const next = !sharing;
      await room.localParticipant.setScreenShareEnabled(next);
      setSharing(next);
    } catch (err) {
      // User cancelled OS picker or unsupported
      setSharing(false);
      if (err?.name !== 'NotAllowedError') {
        dispatch(setCallError(err?.message || 'Не удалось включить демонстрацию экрана'));
      }
    }
  };

  const incomingOpen = Boolean(incoming && resolveCallId(incoming.id)) && !session;
  const inCallOpen = Boolean(session?.token && resolveCallId(session?.call?.id));
  const incomingCallId = resolveCallId(incoming?.id);

  const title =
    session?.call?.conversation_type === 'group'
      ? session?.call?.conversation_title || 'Групповой звонок'
      : session?.call?.creator?.id === currentUser?.id
        ? 'Исходящий звонок'
        : session?.call?.creator?.name || 'Звонок';

  return (
    <>
      <Dialog open={incomingOpen} maxWidth="xs" fullWidth>
        <DialogContent>
          <Stack spacing={2} alignItems="center" sx={{ py: 2 }}>
            <Avatar
              src={incoming?.creator?.avatar_url || undefined}
              sx={{ width: 72, height: 72 }}
            >
              {getInitials(incoming?.creator?.name)}
            </Avatar>
            <Typography variant="h6" align="center">
              {incoming?.conversation_type === 'group'
                ? `Звонок в «${incoming?.conversation_title || 'группе'}»`
                : `Звонок от ${incoming?.creator?.name || 'пользователя'}`}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              {incoming?.media_type === 'video' ? 'Видеозвонок' : 'Аудиозвонок'}
            </Typography>
            <Stack direction="row" spacing={2}>
              <Button
                variant="outlined"
                color="inherit"
                disabled={incomingCallId == null}
                onClick={() => {
                  if (incomingCallId != null) {
                    dispatch(rejectCall(incomingCallId));
                  }
                }}
              >
                Отклонить
              </Button>
              <Button
                variant="contained"
                color="success"
                startIcon={<CallIcon />}
                disabled={incomingCallId == null}
                onClick={() => {
                  if (incomingCallId == null) return;
                  if (incoming.conversation_type === 'group' && incoming.status === 'active') {
                    dispatch(joinCall(incomingCallId));
                  } else {
                    dispatch(acceptCall(incomingCallId));
                  }
                }}
              >
                {incoming?.conversation_type === 'group' && incoming?.status === 'active'
                  ? 'Присоединиться'
                  : 'Принять'}
              </Button>
            </Stack>
          </Stack>
        </DialogContent>
      </Dialog>

      <Dialog
        open={inCallOpen}
        fullScreen
        PaperProps={{ sx: { bgcolor: 'grey.900', color: 'common.white' } }}
      >
        <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column', position: 'relative' }}>
          <Box sx={{ p: 2 }}>
            <Typography variant="h6">{title}</Typography>
            <Typography variant="body2" sx={{ opacity: 0.75 }}>
              {connecting
                ? 'Подключение…'
                : session?.call?.media_type === 'video'
                  ? 'Видеозвонок'
                  : 'Аудиозвонок'}
            </Typography>
            {error && (
              <Alert severity="error" sx={{ mt: 1 }} onClose={() => dispatch(setCallError(null))}>
                {error}
              </Alert>
            )}
          </Box>

          <Box
            sx={{
              flex: 1,
              px: 2,
              display: 'flex',
              flexWrap: 'wrap',
              gap: 1.5,
              alignContent: 'flex-start',
              overflow: 'auto',
            }}
          >
            {remotes.length === 0 ? (
              <Box
                sx={{
                  flex: 1,
                  minHeight: 200,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                }}
              >
                <Typography sx={{ opacity: 0.7 }}>
                  {session?.call?.status === 'ringing'
                    ? 'Ожидание ответа…'
                    : 'Ожидание участников…'}
                </Typography>
              </Box>
            ) : (
              remotes.map((participant) => (
                <RemoteParticipantView key={participant.sid} participant={participant} />
              ))
            )}
          </Box>

          {session?.call?.participants?.length > 0 && (
            <Stack direction="row" spacing={1} sx={{ px: 2, pb: 1 }} useFlexGap flexWrap="wrap">
              <Typography variant="caption" sx={{ opacity: 0.7, alignSelf: 'center' }}>
                В звонке:
              </Typography>
              {session.call.participants.map((p) => (
                <Stack
                  key={p.id}
                  direction="row"
                  spacing={0.5}
                  alignItems="center"
                  sx={{ bgcolor: 'rgba(255,255,255,0.08)', px: 1, py: 0.25, borderRadius: 1 }}
                >
                  <Avatar src={p.avatar_url || undefined} sx={{ width: 20, height: 20, fontSize: 10 }}>
                    {getInitials(p.name)}
                  </Avatar>
                  <Typography variant="caption">{p.name}</Typography>
                </Stack>
              ))}
            </Stack>
          )}

          <LocalPreview room={room} mediaType={session?.call?.media_type} />

          <Stack
            direction="row"
            spacing={1.5}
            justifyContent="center"
            alignItems="center"
            sx={{ p: 2, pb: 3 }}
          >
            <IconButton
              onClick={toggleMic}
              sx={{ bgcolor: 'rgba(255,255,255,0.12)', color: 'common.white' }}
              aria-label={micOn ? 'Выключить микрофон' : 'Включить микрофон'}
            >
              {micOn ? <MicIcon /> : <MicOffIcon />}
            </IconButton>
            {session?.call?.media_type === 'video' && (
              <IconButton
                onClick={toggleCam}
                sx={{ bgcolor: 'rgba(255,255,255,0.12)', color: 'common.white' }}
                aria-label={camOn ? 'Выключить камеру' : 'Включить камеру'}
              >
                {camOn ? <VideocamIcon /> : <VideocamOffIcon />}
              </IconButton>
            )}
            <Tooltip title={shareSupported ? (sharing ? 'Остановить демо' : 'Демонстрация экрана') : screenShareHint()}>
              <span>
                <IconButton
                  onClick={toggleShare}
                  disabled={!shareSupported || !room}
                  sx={{
                    bgcolor: sharing ? 'warning.main' : 'rgba(255,255,255,0.12)',
                    color: 'common.white',
                    '&.Mui-disabled': { color: 'grey.500' },
                  }}
                  aria-label="Демонстрация экрана"
                >
                  {sharing ? <StopScreenShareIcon /> : <ScreenShareIcon />}
                </IconButton>
              </span>
            </Tooltip>
            <IconButton
              onClick={handleHangup}
              sx={{ bgcolor: 'error.main', color: 'common.white', width: 56, height: 56 }}
              aria-label="Завершить звонок"
            >
              <CallEndIcon />
            </IconButton>
          </Stack>
        </Box>
      </Dialog>
    </>
  );
}
