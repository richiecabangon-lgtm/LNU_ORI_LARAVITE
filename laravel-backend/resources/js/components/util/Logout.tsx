import api from "../api/axios";
import { FiLogOut } from "react-icons/fi";
import { useState } from "react";
import LoadingSpinner from "../shared/components/LoadingSpinner";
import { useNavigate } from "react-router-dom";
import { useAuthContextProvider } from "../../hooks/hooks";
import ConfirmationModal from "../shared/components/ConfirmationModal";

const Logout = ({ style }: { style: string }) => {
  const [isLoading, setIsLoading] = useState(false);
  const [openModal, setOpenModal] = useState(false);
  const navigate = useNavigate();
  const { setUser } = useAuthContextProvider();

  const handleLogout = async () => {
    setIsLoading(true);
    try {
      await api.post("/api/logout");
      setUser(null);
      navigate("/login");
    } catch (error) {
      console.error("Logout failed:", error);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <>
      <button
        onClick={() => setOpenModal(true)}
        className={style}
      >
        <FiLogOut className="mr-2" />
        <span>Log out</span>
      </button>
      <ConfirmationModal
        isOpen={openModal}
        title="Confirm Logout"
        message="Are you sure you want to log out?"
        type="warning"
        confirmLabel="Logout"
        cancelLabel="Cancel"
        onCancel={() => setOpenModal(false)}
        onConfirm={() => {
          setOpenModal(false);
          handleLogout();
        }}
      />
      <LoadingSpinner isLoading={isLoading} text="Logging out..." />
    </>
  );
};

export default Logout;
