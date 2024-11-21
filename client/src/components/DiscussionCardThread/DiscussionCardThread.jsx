import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import './DiscussionCardThread.css';

const DiscussionCardThread = ({ thread, handleComment, submitVote }) => {
  const [vote, setVote] = useState(thread?.user_vote || null); // null: no vote, 'upvote': upvoted, 'downvote': downvoted
  const [voteCount, setVoteCount] = useState(thread?.upvotes - thread?.downvotes || 0);

  const handleVote = (newVote) => {
    if (vote === newVote) {
      // If already voted the same way, undo the vote
      setVote(null);
      setVoteCount((prev) => (newVote === 'upvote' ? prev - 1 : prev + 1));
      submitVote(thread.thread_id, "null");
    } else {
      // Apply new vote; adjust vote count based on current state
      setVote(newVote);
      setVoteCount((prev) => {
        if (newVote === 'upvote') {
          return vote === 'downvote' ? prev + 2 : prev + 1;
        } else {
          return vote === 'upvote' ? prev - 2 : prev - 1;
        }
      });
      submitVote(thread.thread_id, newVote);
    }
    console.log(thread.thread_id, newVote);
  };

  return (
    <div className="p-3 discussion-card-thread">
      <div className="d-flex flex-column">
        {/* Thread Metadata */}
        <div className="d-flex gap-2 w-100 mb-2 align-bottom align-items-center author-section">
          <Link to={`/profile/${thread?.author?.alumni_id}`}>
            <img
              src={thread?.author?.profile_picture || "https://static.vecteezy.com/system/resources/thumbnails/009/292/244/small/default-avatar-icon-of-social-media-user-vector.jpg"}
              className="img-fluid rounded-circle alumni-profile-img mr-2"
              alt=""
            />
          </Link>
          <div className='d-flex flex-column'>
            <Link style={{ textDecoration: 'none' }}  to={`/profile/${thread?.author?.alumni_id}`}>
              <p className="thread-author">{thread?.author?.name}</p>
            </Link>
            <div className='d-flex gap-2 align-bottom align-items-center'>
              <p className="thread-num">Active {thread?.updated_at}</p>
              <span>&middot;</span>
              <p className="thread-num">{thread?.views} views</p>
            </div>
          </div>
        </div>

        {/* Thread Title */}
        <h3 className="thread-title mb-2">{thread?.title}</h3>

        {/* Thread Tags */}
        <div className="d-flex flex-wrap gap-2">
          {thread?.tags?.map((tag) => (
            <div
              key={tag?.tag_id}
              className="d-flex justify-content-start align-items-center tag-container"
            >
              <p className="thread-tag">{tag?.name}</p>
            </div>
          ))}
        </div>


        {/* Thread Body */}
        <p className="thread-body my-1">{thread?.description}</p>

        {/* Thread Image */}
        <div className="image-container">
          <img src="https://placehold.co/500x800" alt={thread?.title} className="thread-image" />
        </div>

        {/* Thread Actions */}
        <div className="thread-actions d-flex gap-2 mt-2">
          <div className="btn-group vote-group" role="vote" aria-label="Vote Buttons">
            {/* Upvote Button */}
            <button
              className={`btn btn-primary upvote ${vote === 'upvote' ? 'active' : ''}`}
              onClick={() => handleVote('upvote')}
            >
              <i className="fas fa-up-long"></i>
            </button>

            {/* Vote Count */}
            <button
              className={`btn vote-count ${vote === 'upvote' ? 'upvote' : vote === 'downvote' ? 'downvote' : ''}`}
              disabled
            >
              {voteCount}
            </button>

            {/* Downvote Button */}
            <button
              className={`btn btn-primary downvote ${vote === 'downvote' ? 'active' : ''}`}
              onClick={() => handleVote('downvote')}
            >
              <i className="fas fa-down-long"></i>
            </button>
          </div>

          {/* Reply Button */}
          <button
            className="btn btn-reply d-inline-flex gap-2 align-items-center justify-content-center"
            onClick={handleComment}
          >
            <i className="fas fa-reply"></i>
            {thread?.comments_count || thread?.comment?.length}
          </button>
        </div>
      </div>
    </div>
  );
};

export default DiscussionCardThread;
