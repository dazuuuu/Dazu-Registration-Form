import { useOutletContext } from 'react-router-dom';
import Homepage from '../components/Homepage';

export default function HomepageRoute() {
  const { onGetStartedClick } = useOutletContext<{
    onGetStartedClick: (courseName?: string, courseId?: number) => void;
  }>();
  return <Homepage onGetStartedClick={onGetStartedClick} />;
}
